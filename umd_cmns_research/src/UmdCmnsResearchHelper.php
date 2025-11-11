<?php

namespace Drupal\umd_cmns_research;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class UmdCmnsResearchHelper {

  /**
   * This helper function uses orcid work id to populate field_authors
   * in Paper nodes.
   */  
	private function getDataFromOrcid($orcid_workid, $orcid_id) {
  	$client = new Client();
  	// example URL - https://pub.orcid.org/v3.0/0000-0001-7402-0432/work/183194287
  	$orcid_work_endpoint = 'https://pub.orcid.org/v3.0/' . $orcid_id . '/work/' . $orcid_workid;   	
  	$contributors_array = []; //storage for author ids
 	
  	try {

    	// inject key module service to get bearer token
    	$key_id = 'umd_orcid_token';
			$key_entity = \Drupal::service('key.repository')->getKey($key_id);			
			$key_entity_value = '';

			if (isset($key_entity)) {
				$key_entity_value = $key_entity->getKeyValue();			
			}

    	$response = $client->get($orcid_work_endpoint, [
      	'headers' => [
        	'Authorization' => $key_entity_value,
        	'Content-Type' => 'application/vnd.orcid+xml',
      	],
    	]);

			$results = $response->getBody()->getContents();

			if ($results) {	
				// remove extra selectors so that the php simplexml function will work properly
				$xml = str_replace('xmlns:internal="http://www.orcid.org/ns/internal" xmlns:education="http://www.orcid.org/ns/education" xmlns:distinction="http://www.orcid.org/ns/distinction" xmlns:deprecated="http://www.orcid.org/ns/deprecated" xmlns:other-name="http://www.orcid.org/ns/other-name" xmlns:membership="http://www.orcid.org/ns/membership" xmlns:error="http://www.orcid.org/ns/error" xmlns:common="http://www.orcid.org/ns/common" xmlns:record="http://www.orcid.org/ns/record" xmlns:personal-details="http://www.orcid.org/ns/personal-details" xmlns:keyword="http://www.orcid.org/ns/keyword" xmlns:email="http://www.orcid.org/ns/email" xmlns:external-identifier="http://www.orcid.org/ns/external-identifier" xmlns:funding="http://www.orcid.org/ns/funding" xmlns:preferences="http://www.orcid.org/ns/preferences" xmlns:address="http://www.orcid.org/ns/address" xmlns:invited-position="http://www.orcid.org/ns/invited-position" xmlns:work="http://www.orcid.org/ns/work" xmlns:history="http://www.orcid.org/ns/history" xmlns:employment="http://www.orcid.org/ns/employment" xmlns:qualification="http://www.orcid.org/ns/qualification" xmlns:service="http://www.orcid.org/ns/service" xmlns:person="http://www.orcid.org/ns/person" xmlns:activities="http://www.orcid.org/ns/activities" xmlns:researcher-url="http://www.orcid.org/ns/researcher-url" xmlns:peer-review="http://www.orcid.org/ns/peer-review" xmlns:bulk="http://www.orcid.org/ns/bulk" xmlns:research-resource="http://www.orcid.org/ns/research-resource"', '', $results);
				$xml = simplexml_load_string($xml);
				$json = json_encode($xml);
				$array = json_decode($json,TRUE);

				$contributors = 0;
				if (isset($array['work:contributors'])) {
					foreach ($array['work:contributors'] as $contributors) {
						if (isset($contributors['work:credit-name'])) {
							$contributors_array[] = $contributors['work:credit-name'];				
						} else {
							foreach ($contributors as $contributor) {
								$contributors_array[] = $contributor['work:credit-name'];
							}				
						}
					}
				}
			} else {
				// no results found
    		\Drupal::messenger()->addStatus(t('No results - ensure that you have populated the umd_orcid_token key with the proper value'));			
			}
    	// Process the data
  	} catch (RequestException $e) {
    	// Handle exceptions
    	\Drupal::messenger()->addStatus(t('No result found with ' . $orcid_work_endpoint));
  	}	
  	
  	return $contributors_array;
	}  

  /**
   * Initial data returned for orcid papers does not include information for all 
   * paper authors. This helper function uses orcid work id to populate field_authors
   * in Paper nodes.
   * Function can be run via cron or drush.
   */  
  public function addAuthorsToPapers() {
  	$nids = \Drupal::entityQuery('node')
    	->condition('type', 'paper')
    	->notExists('field_authors')
		//	->condition('nid', 8400, '>') // skip initial paper imports
			->range(0, 200)
			->sort('created', 'DESC')
      ->accessCheck(FALSE) // Check access for current user.
    	->execute();

    foreach ($nids as $nid) {
  // echo "NID!! " . $nid;
    	$node = Node::load($nid);
    	if ($node) {   		
    		// ID specific to a paper
    		$orcid_workid = $node->get('field_orcid_work_id')->value;   		
    		// Author orcids - multiple values possible
    		$orcid_ids = $node->get('field_orcid_multi')->getValue();  

				foreach ($orcid_ids as $item) {
					echo $orcid_workid . " " . $item['value'] . "\r\n";
    			if ($orcid_workid && $item['value']) {
    				$data = $this->getDataFromOrcid($orcid_workid, $item['value']);  			
    				if ($data) {
    					$node->set('field_authors', $data);
    					$node->save();
    				}
    			}
				}
    	} 
    }   
  	\Drupal::messenger()->addStatus(t('Authors have been updated.'));
  }
}


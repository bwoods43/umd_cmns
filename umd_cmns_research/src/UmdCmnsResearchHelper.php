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
				$xml = simplexml_load_string($results);
				$work = $xml->children('work', true);
				$array = json_decode(json_encode($work), true);

				$contributors = 0;
				if (isset($array['contributors']) && count($array['contributors']) >= 1) {
					foreach ($array['contributors'] as $contributors) {
						if (isset($contributors['credit-name'])) {
							$contributors_array[] = $contributors['credit-name'];				
						} else {
							foreach ($contributors as $contributor) {
								$contributors_array[] = $contributor['credit-name'];
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
    	// Handle exceptions (i.e. workid matches with different orcid)
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
		//	->condition('nid', 5625, '=') // skip initial paper imports
			->range(0, 200)
			->sort('created', 'DESC')
      ->accessCheck(FALSE) // Check access for current user.
    	->execute();

    foreach ($nids as $nid) {
   		echo "NID!! " . $nid . "\r\n";
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


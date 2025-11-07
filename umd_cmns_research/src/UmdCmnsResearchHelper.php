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
    	$response = $client->get($orcid_work_endpoint, [
      	'headers' => [
        	'Authorization' => 'add bearer token here',
        	'Content-Type' => 'application/vnd.orcid+xml',
      	],
    	]);

			$results = $response->getBody()->getContents();
	
			if ($results) {	
				$xml = simplexml_load_string($results);
				$json = json_encode($xml);
				$array = json_decode($json,TRUE);

				$contributors = 0;
				if (isset($array['work:contributors'])) {
					foreach ($array['work:contributors'] as $contributors) {
						if ($contributors['work:credit-name']) {
							$contributors_array[] = $contributors['work:credit-name'];				
						} else {
							foreach ($contributors as $contributor) {
								$contributors_array[] = $contributor['work:credit-name'];
							}				
						}
					}
				}
			}
    	// Process the data
  	} catch (RequestException $e) {
    	// Handle exceptions
    	\Drupal::messenger()->addStatus(t('Update Author error with ' . $orcid_work_endpoint));
  	}	
  	return $contributors_array;
	}


  /**
   * Site should display faculty tags for those faculty members on a given site.
   * This helper function targets the Faculty parent tag, unpublishes all faculty
   * child tags and then republishes all faculty child tags that have a value for
   * field_user_group in a Team node.
   * Function can be run via cron or drush.
   */
	public function unpublishFacultyTags() {

  	$parent_term_id = 240; // Faculty term id on the astronomy site

		// Inject the entity type manager if you are in a class.
		$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

		// Create the entity query.
		$query = $term_storage->getQuery();

		// Filter by vocabulary machine name.
		$query->condition('vid', 'tags');
		$query->condition('parent', $parent_term_id);
 		$query->accessCheck(FALSE); // Check access for current user.

		// Execute the query to get an array of term IDs.
		$tids = $query->execute();

  	if (!empty($tids)) {
  		// Load multiple term entities at once for better performance.
  		$terms = $term_storage->loadMultiple($tids);

  		foreach ($terms as $term) {
    		// Set the value of the custom boolean field to '0' (false).
    		$term->set('status', 0);

    		// Save the term.
    		$term->save();
  		}
  
    	foreach ($tids as $key=>$tid) {
  			$nids = \Drupal::entityQuery('node')
    			->condition('type', 'team')
    			->condition('field_user_group', $tid)
    			->condition('status', 1)
    			//	->range(0,2)
      		->accessCheck(FALSE) // Check access for current user.
    			->execute();

				// if not empty, publish the term
				if (!empty($nids)) {
					$term = Term::load($tid);				
					if ($term) {
  					$term->set('status', 1);
  					$term->save();
  					// \Drupal::messenger()->addStatus(t('The taxonomy term with ID @tid has been published.', ['@tid' => $tid]));
					}		
				}
			}   
  	} 

  	\Drupal::messenger()->addStatus(t('Terms have been processed'));

    return;
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
			->condition('nid', 8617, '>') // skip initial paper imports
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


<?php

namespace Drupal\umd_cmns\Plugin\Tamper;

use Drupal\tamper\TamperableItemInterface;
use Drupal\tamper\TamperBase;

/**
 * Plugin implementation for casting to integer.
 *
 * @Tamper(
 *   id = "rewrite_orcid_authors",
 *   label = @Translation("Rewrite Orcid Authors"),
 *   description = @Translation("Rewrite OrcId Authors"),
 *   category = "Other"
 * )
 */
class RewriteOrcIdAuthors extends TamperBase {

  /**
   * {@inheritdoc}
   */
  public function tamper($data, TamperableItemInterface $item = NULL) {

    $parent_source = '';
    $orcid_authors = [];

    foreach ($item->getSource() as $key => $value) {     
//echo "<PRE>";
//echo $key . " " . $value . "\r\n";

      $trans['[' . $key . ']'] = is_array($value) ? reset($value) : $value;
      if ($key == "external_id") {
        $external_id = $value;
      }              
      if ($key == "parent:source") {
        $parent_source = $value;
      } 
    }
    
    if (!empty($external_id)) {

    	// might be multiple values, but we need only one
			if(is_array($external_id)){
    		$external_id_first = $external_id[0];    		
			} else {
    		$external_id_first = $external_id;			
			}
			    
			$query = \Drupal::entityQuery('node')
  			->condition('type', 'paper')
  			->condition('field_external_ids.value', $external_id_first)
  			->range(0,1)
  			->accessCheck(TRUE);
  			//->__toString();
				//echo $query;
			$result = $query->execute();   

			// only one nid returned
			$paper_nid = reset($result);
		
			// if paper already exists in drupal, get current orcid values and convert to string
			$current_orcid_authors = '';
			if ($paper_nid) {
				$node = \Drupal\node\Entity\Node::load($paper_nid);	
				$current_orcid_authors = $node->get('field_orcid_authors')->getValue();
					
			//print_r($current_orcid_authors);
			//exit;
			
				$current_orcid_authors_string = implode(',', array_map(function ($entry) 
					{
  					return $entry['target_id'];
  				},
  				$current_orcid_authors));	
  						
				$orcid_authors = explode(",", $current_orcid_authors_string);
			}			    
    }
    			
		// add new orcid based on feed orcid
		if ($parent_source) {
			$feed_orcid = str_replace("https://pub.orcid.org/v3.0/", "", $parent_source[0]);
			$feed_orcid = str_replace("/works", "", $feed_orcid);

			// lookup team member node by orcid to add team member to paper
				
			$query = \Drupal::entityQuery('node')
  			->condition('type', 'team')
  			->condition('field_orcid', $feed_orcid)
  			->range(0,1)
  			->accessCheck(TRUE);
  			//->__toString();
				//echo $query;
			$result = $query->execute();   

			// only one nid returned
			$team_nid = reset($result);

			if (!in_array($team_nid, $orcid_authors)) {
    		array_push($orcid_authors, $team_nid);
			}
		}
		
		return $orcid_authors;      
  }
}

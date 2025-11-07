<?php

namespace Drupal\umd_cmns_research\Plugin\Tamper;

use Drupal\tamper\TamperableItemInterface;
use Drupal\tamper\TamperBase;

/**
 * Plugin implementation for casting to integer.
 *
 * @Tamper(
 *   id = "rewrite_orcid",
 *   label = @Translation("Rewrite Orcid"),
 *   description = @Translation("Rewrite OrcId"),
 *   category = "Other"
 * )
 */
class RewriteOrcId extends TamperBase {

  /**
   * {@inheritdoc}
   */
  public function tamper($data, TamperableItemInterface $item = NULL) {

    $bibcode = '';
    $parent_source = '';
     
//print_r($item->getSource());
    foreach ($item->getSource() as $key => $value) {     
//echo "<PRE>";
//print_r($key);
      $trans['[' . $key . ']'] = is_array($value) ? reset($value) : $value;
      if ($key == "external_id") {
        $external_id = $value;
      }           
      if ($key == "parent:source") {
        $parent_source = $value;
      } 
    }
      
  	// lookup node by external id if it exists
    if (!empty($external_id)) {
    
    	// might be multiple values, but we need only one
			if(is_array($external_id)){
    		$external_id_first = $external_id[0];    		
			} else {
    		$external_id_first = $external_id;			
			}
    	    
			$query = \Drupal::entityQuery('node')
  			->condition('type', 'paper')
//  			->condition('field_bibcode', $bibcode)
  			->condition('field_external_ids.value', $external_id_first)
  			->range(0,1)
  			->accessCheck(TRUE);
  			//->__toString();
				//echo $query;
			$result = $query->execute();   

			// only one nid returned
			$nid = reset($result);
		
			// if paper already exists in drupal, get current orcid values and convert to string
			$current_orcids = '';
			$current_orcids_string = '';
			if ($nid) {
				$node = \Drupal\node\Entity\Node::load($nid);	
				$current_orcids = $node->get('field_orcid_multi')->getValue();
			
				$current_orcids_string = implode(',', array_map(function ($entry) {
  				return $entry['value'];
				}, $current_orcids));			
			}
				
			// add new orcid based on feed orcid
			if ($parent_source) {
				$feed_orcid = str_replace("https://pub.orcid.org/v3.0/", "", $parent_source[0]);
				$feed_orcid = str_replace("/works", "", $feed_orcid);
				$current_orcids_string .= "," . $feed_orcid;			
			}
			
			// remove duplicate and convert back to string
			$current_orcids_string = implode(',',array_unique(explode(',', $current_orcids_string)));
			
			return $current_orcids_string;   
    } else {
			return $data;
    
    }
      
  }
}

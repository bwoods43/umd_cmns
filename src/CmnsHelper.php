<?php

namespace Drupal\umd_cmns;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class CmnsHelper {
  /**
   * Site should display faculty tags for those faculty members on a given site.
   * This helper function targets the Faculty parent tag, unpublishes all faculty
   * child tags and then republishes all faculty child tags that have a value for
   * field_user_group in a Team node.
   * Function can be run via cron or drush.
   */
	public function unpublishFacultyTags() {

		// get Faculty term id because this can be different on sites
		$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

		$query = $term_storage->getQuery();
		$query->condition('vid', 'tags');
		$query->condition('name', 'Faculty');
		$query->condition('status', 0); // Faculty tag should be unpublished - this is to avoid an accidental duplication on the Faculty tag
 		$query->accessCheck(FALSE); // Check access for current user.

		$tids = $query->execute();
		
		if ($tids) {
  		$parent_term_id = reset($tids);
		}		

		if (isset($parent_term_id)) {
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
  					 	\Drupal::messenger()->addStatus(t('The taxonomy term with ID @tid has been published.', ['@tid' => $tid]));
						}		
					}
				}   
  		} 

  		\Drupal::messenger()->addStatus(t('Terms have been processed'));
  	} else {
  		\Drupal::messenger()->addStatus(t('Could not find a faculty id'));  	
  	}
  	
    return;
  }
}


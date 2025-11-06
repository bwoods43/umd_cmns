<?php

namespace Drupal\umd_cmns\Drush\Commands;

use Drupal\Core\Utility\Token;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Drush commandfile.
 */
final class UmdCmnsCommands extends DrushCommands {

  /**
   * Constructs an UmdCmnsCommands object.
   */
  public function __construct(
    private readonly Token $token,
  ) {
    parent::__construct();
    $this->encoder = \Drupal::service('serializer');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('token'),
    );
  }

// see https://www.fourkitchens.com/blog/development/custom-drush-commands-drush-generate/ for full tutorial

	/**
   * Add authors to papers via orcid
   *
   * @command umd_cmns:add-authors-to-papers
   * @aliases addauthors
   * @usage umd_cmns:add-authors-to-papers 
   *   Adds authors from orcid to papers that are created after initial import 9.12.25
   */
  public function addAuthorsToPapers() {
  	\Drupal::service('umd_cmns.cmns_helper')->addAuthorsToPapers();      
    \Drupal::messenger()->addStatus('drush command was executed.');
  }
  
	/**
   * Unpublish faculty tags not needed on the site
   *
   * @command umd_cmns:unpublish-faculty-tags-not-on-site
   * @aliases unpubfactags
   * @usage umd_cmns:unpublish-faculty-tags-not-on-site 
   *   Unpublishes all faculty tags, then publishes those faculty tags that find 
   *   a match for a published team member on the site
   */
	public function unPublishFacultyTags() {
  	\Drupal::service('umd_cmns.cmns_helper')->unpublishFacultyTags();      
    \Drupal::messenger()->addStatus('drush command was executed.');
  }   
}

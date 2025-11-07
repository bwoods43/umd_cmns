<?php

namespace Drupal\umd_cmns_research\Drush\Commands;

use Drupal\Core\Utility\Token;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Drush commandfile.
 */
final class UmdCmnsResearchCommands extends DrushCommands {

  /**
   * Constructs an UmdCmnsResearchCommands object.
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

	/**
   * Add authors to papers via orcid
   *
   * @command umd_cmns_research:add-authors-to-papers
   * @aliases addauthors
   * @usage umd_cmns_research:add-authors-to-papers 
   *   Adds authors from orcid to papers that are created after initial import 9.12.25
   */
  public function addAuthorsToPapers() {
  	\Drupal::service('umd_cmns_research.umd_cmns_research_helper')->addAuthorsToPapers();      
    \Drupal::messenger()->addStatus('drush command was executed.');
  }  
}

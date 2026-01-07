UMD CMNS Research
=========
This module contains the functionality to store and display research papers that are available at orcid.org.

Several Drupal contrib modules are needed to use this module:
- feeds
- key
- custom_field
- feeds_ex
- feeds_http_auth_fetcher
- feeds_tamper

This module assume that field_orcid exists on the Team content type. If your site doesn't include that already, make sure to create by using the Repo field as an example.

To display the research papers, be sure to add the blocks (per person and overall publications) on the block layout page.
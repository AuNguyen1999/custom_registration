<?php

namespace Drupal\custom_registration\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for the registration thank you page.
 */
class RegistrationThankYouController extends ControllerBase {

  /**
   * Renders the registration thank you page.
   *
   * @return array
   *   A render array representing the thank you page.
   */
  public function thankYou(): array {
    return [
      '#theme' => 'thank_you_page',
    ];
  }

}

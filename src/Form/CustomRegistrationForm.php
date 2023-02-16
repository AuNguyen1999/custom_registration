<?php

namespace Drupal\custom_registration\Form;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\FieldOverride;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Build form register.
 */
class CustomRegistrationForm extends FormBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The user storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userStorage;

  /**
   * CustomRegistrationForm constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(LanguageManagerInterface $languageManager, MailManagerInterface $mailManager, EntityTypeManagerInterface $entityTypeManager) {
    $this->languageManager = $languageManager;
    $this->mailManager = $mailManager;
    $this->userStorage = $entityTypeManager->getStorage('user');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('plugin.manager.mail'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'custom_registration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#tree'] = TRUE;
    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
    ];
    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
    ];
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];
    $form['password'] = [
      '#type' => 'password_confirm',
      '#required' => TRUE,
    ];
    $form['address_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Address'),
      '#open' => TRUE,
    ];
    $form['address_wrapper']['address'] = [
      '#type' => 'address',
      '#field_overrides' => [
        AddressField::GIVEN_NAME => FieldOverride::HIDDEN,
        AddressField::FAMILY_NAME => FieldOverride::HIDDEN,
        AddressField::ADDITIONAL_NAME => FieldOverride::HIDDEN,
      ],
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $email = $form_state->getValue('email');
    if (!empty($email)) {
      $account = $this->userStorage->loadByProperties(['mail' => $email]);
      if (!empty($account)) {
        $form_state->setErrorByName('email', $this->t('The email address %value is already in use. Please choose a different email address.', ['%value' => $email]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $first_name = $form_state->getValue('first_name');
    $last_name = $form_state->getValue('last_name');
    $email = $form_state->getValue('email');
    $password = $form_state->getValue('password');
    $address = $form_state->getValue([
      'address_wrapper',
      'address',
    ]);

    $user = User::create();
    $user->setUsername($email);
    $user->setEmail($email);
    $user->setPassword($password);
    $user->set('init', $email);
    $user->set('status', 1);
    $user->set('field_first_name', $first_name);
    $user->set('field_last_name', $last_name);
    if (!empty($address)) {
      $user->set('field_address', $address);
    };

    $user->enforceIsNew();
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $user->set('langcode', $langcode);
    $user->set('preferred_langcode', $langcode);
    $user->set('preferred_admin_langcode', $langcode);
    $user->save();

    $module = 'custom_registration';
    $key = 'account_created';
    $to = $form_state->getValue('email');
    $params['subject'] = $this->t('Your account has been created.');
    $params['body'] = [
      '#theme' => 'register_success_mail',
      '#user' => $user,
    ];
    $this->mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);

    $form_state->setRedirect('custom_registration.thank_you');
  }

}

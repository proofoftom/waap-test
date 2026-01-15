<?php

declare(strict_types=1);

namespace Drupal\wallet_auth\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the wallet address entity edit forms.
 *
 * Provides a guardrail for editing the wallet_address field by requiring
 * explicit acknowledgment before allowing changes.
 *
 * Also provides user reassignment functionality to transfer wallet ownership
 * to a different user account.
 */
class WalletAddressForm extends ContentEntityForm {

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Constructs a WalletAddressForm.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   */
  public function __construct(
    $entity_repository,
    $entity_type_bundle_info,
    $time,
    LoggerChannelFactoryInterface $logger_factory,
    UserStorageInterface $user_storage,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->logger = $logger_factory->get('wallet_auth');
    $this->userStorage = $user_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('logger.factory'),
      $container->get('entity_type.manager')->getStorage('user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    // Only add the guardrail on edit forms, not when adding a new entity.
    if (!$this->entity->isNew()) {
      // Disable the wallet_address field by default.
      if (isset($form['wallet_address'])) {
        $form['wallet_address']['widget'][0]['value']['#disabled'] = TRUE;
      }

      // Add a warning container with checkbox to enable editing.
      $form['wallet_address_warning'] = [
        '#type' => 'container',
        '#weight' => isset($form['wallet_address']['#weight']) ? $form['wallet_address']['#weight'] + 0.1 : -4,
        '#attributes' => [
          'class' => ['wallet-address-edit-warning', 'messages', 'messages--warning'],
        ],
      ];

      // phpcs:ignore Drupal.Files.LineLength.TooLong
      $warning_text = $this->t('Changing this value will break the link between this record and the actual blockchain wallet. The user will no longer be able to authenticate with their original wallet.');
      $form['wallet_address_warning']['description'] = [
        '#type' => 'markup',
        '#markup' => '<p><strong>' . $this->t('This field should rarely need editing.') . '</strong></p>' .
        '<p>' . $this->t('Legitimate reasons to edit this field include:') . '</p>' .
        '<ul>' .
        '<li>' . $this->t('Correcting a checksum case mismatch (e.g., 0xAbC vs 0xabc)') . '</li>' .
        '<li>' . $this->t('Fixing a truncated address from a migration error') . '</li>' .
        '</ul>' .
        '<p><strong>' . $this->t('Warning:') . '</strong> ' . $warning_text . '</p>',
      ];

      $form['wallet_address_warning']['enable_wallet_address_edit'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable wallet address editing'),
        '#default_value' => FALSE,
      ];

      // Use #states to enable the field when checkbox is checked.
      if (isset($form['wallet_address'])) {
        $form['wallet_address']['widget'][0]['value']['#states'] = [
          'disabled' => [
            ':input[name="enable_wallet_address_edit"]' => ['checked' => FALSE],
          ],
        ];
      }

      // Add user reassignment section.
      $this->buildReassignmentSection($form, $form_state);
    }

    return $form;
  }

  /**
   * Builds the user reassignment section of the form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function buildReassignmentSection(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\wallet_auth\Entity\WalletAddressInterface $entity */
    $entity = $this->entity;

    $current_owner = $entity->getOwner();
    $current_owner_label = $current_owner
      ? $current_owner->getDisplayName() . ' (uid: ' . $current_owner->id() . ')'
      : $this->t('Unknown user');

    $form['reassignment'] = [
      '#type' => 'details',
      '#title' => $this->t('Reassign to different user'),
      '#open' => FALSE,
      '#weight' => 100,
    ];

    $form['reassignment']['current_owner_display'] = [
      '#type' => 'item',
      '#title' => $this->t('Current owner'),
      '#markup' => $current_owner_label,
    ];

    $form['reassignment']['reassign'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reassign this wallet to a different user'),
      '#default_value' => FALSE,
      '#description' => $this->t('Check this box to transfer ownership of this wallet address to another user.'),
    ];

    $form['reassignment']['target_user'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('New owner'),
      '#target_type' => 'user',
      '#selection_settings' => [
        'include_anonymous' => FALSE,
      ],
      '#description' => $this->t('Search for and select the user who should own this wallet address.'),
      '#states' => [
        'visible' => [
          ':input[name="reassign"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="reassign"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['reassignment']['reassignment_help'] = [
      '#type' => 'details',
      '#title' => $this->t('When to use this'),
      '#open' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="reassign"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['reassignment']['reassignment_help']['use_cases'] = [
      '#theme' => 'item_list',
      '#items' => [
        // phpcs:ignore Drupal.Files.LineLength.TooLong
        $this->t('User created multiple accounts using different WaaP authentication methods (e.g., Google vs Discord social login)'),
        $this->t('User wants to consolidate wallets to their primary account'),
        $this->t('Admin needs to fix an incorrectly linked wallet'),
      ],
      '#title' => $this->t('Common use cases:'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    /** @var \Drupal\wallet_auth\Entity\WalletAddressInterface $entity */
    $entity = $this->entity;

    // Only validate reassignment on edit forms.
    if (!$entity->isNew() && $form_state->getValue('reassign')) {
      $target_user_id = $form_state->getValue('target_user');

      // Check if target user is provided.
      if (empty($target_user_id)) {
        $form_state->setErrorByName(
          'target_user',
          $this->t('Please select a user to reassign this wallet to.')
        );
        return;
      }

      // Load the target user.
      $target_user = $this->userStorage->load($target_user_id);

      if (!$target_user) {
        $form_state->setErrorByName(
          'target_user',
          $this->t('The selected user does not exist.')
        );
        return;
      }

      // Check if reassigning to the same user (no-op warning).
      $current_owner_id = $entity->getOwnerId();
      if ($target_user_id == $current_owner_id) {
        $form_state->setErrorByName(
          'target_user',
          $this->t('This wallet is already assigned to the selected user. Please choose a different user.')
        );
        return;
      }

      // Check if target user is blocked/inactive.
      if ($target_user->isBlocked()) {
        $form_state->setErrorByName(
          'target_user',
          $this->t('Cannot reassign to a blocked user. Please select an active user account.')
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\wallet_auth\Entity\WalletAddressInterface $entity */
    $entity = $this->entity;
    $reassignment_performed = FALSE;

    // Handle reassignment if requested.
    if (!$entity->isNew() && $form_state->getValue('reassign')) {
      $target_user_id = $form_state->getValue('target_user');

      if (!empty($target_user_id)) {
        $old_owner = $entity->getOwner();
        $old_owner_id = $entity->getOwnerId();
        $old_owner_name = $old_owner ? $old_owner->getDisplayName() : $this->t('Unknown');

        // Update the owner.
        $entity->setOwnerId($target_user_id);

        // Load new owner for logging and messaging.
        $new_owner = $this->userStorage->load($target_user_id);
        $new_owner_name = $new_owner ? $new_owner->getDisplayName() : $this->t('Unknown');

        // Log the reassignment for audit purposes.
        $this->logger->notice(
          'Wallet address @address reassigned from user @old_user (uid: @old_uid) to user @new_user (uid: @new_uid) by @admin.',
          [
            '@address' => $entity->getWalletAddress(),
            '@old_user' => $old_owner_name,
            '@old_uid' => $old_owner_id,
            '@new_user' => $new_owner_name,
            '@new_uid' => $target_user_id,
            '@admin' => $this->currentUser()->getDisplayName(),
          ]
        );

        $reassignment_performed = TRUE;
      }
    }

    // Save the entity.
    $status = parent::save($form, $form_state);

    // Show appropriate message.
    if ($reassignment_performed) {
      $old_owner_name = $old_owner_name ?? $this->t('Unknown');
      $new_owner_name = $new_owner_name ?? $this->t('Unknown');
      $this->messenger()->addStatus($this->t(
        'Wallet address @address has been reassigned from @old_user to @new_user.',
        [
          '@address' => $entity->getWalletAddress(),
          '@old_user' => $old_owner_name,
          '@new_user' => $new_owner_name,
        ]
      ));
    }
    else {
      $entity_link = $entity->toLink($this->t('View'))->toString();
      switch ($status) {
        case SAVED_NEW:
          $this->messenger()->addStatus($this->t('Created wallet address %label. @link', [
            '%label' => $entity->getWalletAddress(),
            '@link' => $entity_link,
          ]));
          break;

        default:
          $this->messenger()->addStatus($this->t('Saved wallet address %label. @link', [
            '%label' => $entity->getWalletAddress(),
            '@link' => $entity_link,
          ]));
      }
    }

    $form_state->setRedirectUrl($entity->toUrl('collection'));

    return $status;
  }

}

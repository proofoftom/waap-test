<?php

declare(strict_types=1);

namespace Drupal\wallet_auth;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\wallet_auth\Entity\WalletAddressInterface;

/**
 * Provides a list controller for wallet address entities.
 *
 * @ingroup wallet_auth
 */
class WalletAddressListBuilder extends EntityListBuilder {

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new WalletAddressListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, RedirectDestinationInterface $redirect_destination, DateFormatterInterface $date_formatter) {
    parent::__construct($entity_type, $storage);
    $this->redirectDestination = $redirect_destination;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('redirect.destination'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [
      'id' => [
        'data' => $this->t('ID'),
        'specifier' => 'id',
        'field' => 'id',
      ],
      'wallet_address' => [
        'data' => $this->t('Wallet Address'),
        'specifier' => 'wallet_address',
        'field' => 'wallet_address',
      ],
      'uid' => [
        'data' => $this->t('User'),
        'specifier' => 'uid',
        'field' => 'uid',
      ],
      'created' => [
        'data' => $this->t('Created'),
        'specifier' => 'created',
        'field' => 'created',
      ],
      'last_used' => [
        'data' => $this->t('Last Used'),
        'specifier' => 'last_used',
        'field' => 'last_used',
      ],
      'status' => [
        'data' => $this->t('Status'),
        'specifier' => 'status',
        'field' => 'status',
      ],
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof WalletAddressInterface);
    $row['id'] = $entity->id();
    $row['wallet_address']['data'] = [
      '#type' => 'link',
      '#title' => $entity->getWalletAddress(),
      '#url' => $entity->toUrl('canonical'),
    ];

    // Handle orphaned wallets where the user was deleted.
    $owner = $entity->getOwner();
    if ($owner && $owner->id()) {
      $row['uid']['data'] = [
        '#type' => 'link',
        '#title' => $owner->getDisplayName(),
        '#url' => $owner->toUrl('canonical'),
      ];
    }
    else {
      $row['uid']['data'] = [
        '#markup' => $this->t('<em>Deleted user (uid: @uid)</em>', [
          '@uid' => $entity->getOwnerId(),
        ]),
      ];
    }

    $row['created'] = $this->dateFormatter->format($entity->getCreatedTime(), 'short');
    $row['last_used'] = $this->dateFormatter->format($entity->getLastUsedTime(), 'short');
    $row['status'] = $entity->isActive() ? $this->t('Active') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('No wallet addresses available.');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);

    // Add the current path as a redirect to the operation links.
    $destination = $this->redirectDestination->getAsArray();
    foreach ($operations as $key => $operation) {
      $operations[$key]['query'] = $destination;
    }

    return $operations;
  }

}

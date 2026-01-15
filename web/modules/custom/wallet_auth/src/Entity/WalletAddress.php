<?php

declare(strict_types=1);

namespace Drupal\wallet_auth\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the wallet address entity.
 *
 * @ContentEntityType(
 *   id = "wallet_address",
 *   label = @Translation("Wallet Address"),
 *   label_collection = @Translation("Wallet Addresses"),
 *   label_singular = @Translation("wallet address"),
 *   label_plural = @Translation("wallet addresses"),
 *   label_count = @PluralTranslation(
 *     singular = "@count wallet address",
 *     plural = "@count wallet addresses",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\wallet_auth\WalletAddressListBuilder",
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\wallet_auth\Form\WalletAddressForm",
 *       "edit" = "Drupal\wallet_auth\Form\WalletAddressForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "wallet_auth_wallet_address",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/people/wallets",
 *     "canonical" = "/admin/people/wallets/{wallet_address}",
 *     "edit-form" = "/admin/people/wallets/{wallet_address}/edit",
 *     "delete-form" = "/admin/people/wallets/{wallet_address}/delete",
 *   },
 *   admin_permission = "administer wallet addresses",
 * )
 */
class WalletAddress extends ContentEntityBase implements WalletAddressInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function getWalletAddress(): string {
    return $this->get('wallet_address')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setWalletAddress(string $address): WalletAddressInterface {
    $this->set('wallet_address', $address);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): WalletAddressInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastUsedTime(): int {
    return (int) $this->get('last_used')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastUsedTime(int $timestamp): WalletAddressInterface {
    $this->set('last_used', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isActive(): bool {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setActive(bool $status): WalletAddressInterface {
    $this->set('status', (int) $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['wallet_address'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Wallet Address'))
      ->setDescription(t('The Ethereum wallet address.'))
      ->setSetting('max_length', 42)
      ->setRequired(TRUE)
      ->addConstraint('UniqueField')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created on'))
      ->setDescription(t('The time that the wallet address was first linked.'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'region' => 'hidden',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the wallet address was last edited.'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'region' => 'hidden',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['last_used'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Last Used'))
      ->setDescription(t('The time that the wallet address was last used for authentication.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active'))
      ->setDescription(t('Whether the wallet address is active.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 120,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}

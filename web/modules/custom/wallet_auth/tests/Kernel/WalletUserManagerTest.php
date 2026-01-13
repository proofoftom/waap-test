<?php

declare(strict_types=1);

namespace Drupal\Tests\wallet_auth\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\wallet_auth\Service\WalletUserManager;

/**
 * Tests wallet user management service.
 *
 * @coversDefaultClass \Drupal\wallet_auth\Service\WalletUserManager
 * @group wallet_auth
 */
class WalletUserManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'externalauth',
    'wallet_auth',
  ];

  /**
   * The wallet user manager service.
   *
   * @var \Drupal\wallet_auth\Service\WalletUserManager
   */
  protected $walletUserManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('wallet_auth', ['wallet_auth_wallet_address']);
    $this->installSchema('externalauth', ['authmap']);

    $this->walletUserManager = $this->container->get('wallet_auth.user_manager');

    // Ensure clean database for each test.
    $this->cleanupTestData();
  }

  /**
   * Cleanup test data.
   */
  protected function cleanupTestData(): void {
    // Delete all test users.
    $uids = $this->container->get('entity_type.manager')->getStorage('user')
      ->getQuery()
      ->condition('name', 'wallet_%', 'LIKE')
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($uids)) {
      $users = User::loadMultiple($uids);
      foreach ($users as $user) {
        $user->delete();
      }
    }

    // Delete all test users.
    $testUids = $this->container->get('entity_type.manager')->getStorage('user')
      ->getQuery()
      ->condition('name', 'test_user%', 'LIKE')
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($testUids)) {
      $users = User::loadMultiple($testUids);
      foreach ($users as $user) {
        $user->delete();
      }
    }

    // Clear wallet address table.
    $this->container->get('database')->truncate('wallet_auth_wallet_address')->execute();
    // Clear authmap table.
    $this->container->get('database')->truncate('authmap')->execute();
  }

  /**
   * Tests username generation from wallet address.
   */
  public function testGenerateUsernameFromWallet(): void {
    $walletAddress = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';

    // Use reflection to access protected method.
    $reflection = new \ReflectionClass($this->walletUserManager);
    $method = $reflection->getMethod('generateUsername');
    $method->setAccessible(TRUE);

    $username = $method->invoke($this->walletUserManager, $walletAddress);

    // Username should be wallet_ + first 8 hex chars of SHA256 hash of address.
    // Hash output is lowercase.
    $this->assertEquals('wallet_849745fa', $username);
  }

  /**
   * Tests username collision handling.
   */
  public function testGenerateUsernameCollision(): void {
    $walletAddress = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';

    $reflection = new \ReflectionClass($this->walletUserManager);
    $method = $reflection->getMethod('generateUsername');
    $method->setAccessible(TRUE);

    // Create first user with base username.
    $firstUsername = $method->invoke($this->walletUserManager, $walletAddress);
    $this->assertEquals('wallet_849745fa', $firstUsername);

    // Manually create user to simulate collision.
    // Note: ExternalAuth adds 'wallet_auth_' prefix, so we need to create with that prefix.
    $user = User::create([
      'name' => 'wallet_auth_' . $firstUsername,
      'mail' => $firstUsername . '@example.com',
      'status' => 1,
    ]);
    $user->save();

    // Generate username again - should add suffix.
    $secondUsername = $method->invoke($this->walletUserManager, $walletAddress);
    $this->assertEquals('wallet_849745fa_1', $secondUsername);

    // Create another user with the suffixed name.
    $user2 = User::create([
      'name' => 'wallet_auth_' . $secondUsername,
      'mail' => $secondUsername . '@example.com',
      'status' => 1,
    ]);
    $user2->save();

    // Generate username again - should increment suffix.
    $thirdUsername = $method->invoke($this->walletUserManager, $walletAddress);
    $this->assertEquals('wallet_849745fa_2', $thirdUsername);
  }

  /**
   * Tests that generated username is unique.
   */
  public function testGenerateUsernameUnique(): void {
    $walletAddress1 = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';
    $walletAddress2 = '0x1234567890123456789012345678901234567890';

    $reflection = new \ReflectionClass($this->walletUserManager);
    $method = $reflection->getMethod('generateUsername');
    $method->setAccessible(TRUE);

    $username1 = $method->invoke($this->walletUserManager, $walletAddress1);
    $username2 = $method->invoke($this->walletUserManager, $walletAddress2);

    // Usernames should be different for different wallet addresses.
    $this->assertNotEquals($username1, $username2);
  }

  /**
   * Tests user creation from wallet address.
   */
  public function testCreateUserFromWallet(): void {
    $walletAddress = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';

    $user = $this->walletUserManager->createUserFromWallet($walletAddress);

    $this->assertInstanceOf(User::class, $user);
    $this->assertStringStartsWith('wallet_', $user->getAccountName());
    $this->assertStringEndsWith('@wallet.local', $user->getEmail());
    $this->assertTrue($user->isActive());
  }

  /**
   * Tests user email generation.
   */
  public function testCreateUserEmailGeneration(): void {
    $walletAddress = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';

    $user = $this->walletUserManager->createUserFromWallet($walletAddress);

    $this->assertStringEndsWith('@wallet.local', $user->getEmail());
    // Email should be based on username, not the externalauth prefix.
    $this->assertMatchesRegularExpression('/.+@wallet\.local/', $user->getEmail());
  }

  /**
   * Tests wallet linking to created user.
   */
  public function testCreateUserWalletLinking(): void {
    $walletAddress = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';

    $user = $this->walletUserManager->createUserFromWallet($walletAddress);

    // Check that wallet is linked in database.
    $database = $this->container->get('database');
    $linkedUid = $database->select('wallet_auth_wallet_address', 'wa')
      ->fields('wa', ['uid'])
      ->condition('wa.wallet_address', $walletAddress)
      ->execute()
      ->fetchField();

    $this->assertEquals($user->id(), $linkedUid);
  }

  /**
   * Tests that created user is active.
   */
  public function testCreateUserActivation(): void {
    $walletAddress = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';

    $user = $this->walletUserManager->createUserFromWallet($walletAddress);

    $this->assertTrue($user->isActive());
  }

  /**
   * Tests linking a wallet to a user.
   */
  public function testLinkWalletToUser(): void {
    $walletAddress = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';

    // Create a user first.
    $user = User::create([
      'name' => 'test_user',
      'mail' => 'test@example.com',
      'status' => 1,
    ]);
    $user->save();

    // Link wallet to user.
    $this->walletUserManager->linkWalletToUser($walletAddress, (int) $user->id());

    // Verify link in database.
    $database = $this->container->get('database');
    $linkedUid = $database->select('wallet_auth_wallet_address', 'wa')
      ->fields('wa', ['uid'])
      ->condition('wa.wallet_address', $walletAddress)
      ->execute()
      ->fetchField();

    $this->assertEquals($user->id(), $linkedUid);
  }

  /**
   * Tests linking wallet to existing user updates last_used.
   */
  public function testLinkWalletToExistingUser(): void {
    $walletAddress = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';

    $user = User::create([
      'name' => 'test_user',
      'mail' => 'test@example.com',
      'status' => 1,
    ]);
    $user->save();

    // Mock time service for first link (initial time).
    $initialTime = 1600000000;
    $mockTime = $this->createMock(TimeInterface::class);
    $mockTime->method('getRequestTime')->willReturn($initialTime);

    // Create a new instance with mocked time.
    $walletUserManager = new WalletUserManager(
      $this->container->get('database'),
      $this->container->get('externalauth.externalauth'),
      $this->container->get('entity_type.manager'),
      $this->container->get('logger.factory'),
      $mockTime
    );

    // Link wallet first time.
    $walletUserManager->linkWalletToUser($walletAddress, (int) $user->id());

    $database = $this->container->get('database');
    $firstLastUsed = $database->select('wallet_auth_wallet_address', 'wa')
      ->fields('wa', ['last_used'])
      ->condition('wa.wallet_address', $walletAddress)
      ->execute()
      ->fetchField();

    // Mock time service for second link (later time).
    $laterTime = $initialTime + 100;
    $mockTime2 = $this->createMock(TimeInterface::class);
    $mockTime2->method('getRequestTime')->willReturn($laterTime);

    // Create a new instance with new mocked time.
    $walletUserManager2 = new WalletUserManager(
      $this->container->get('database'),
      $this->container->get('externalauth.externalauth'),
      $this->container->get('entity_type.manager'),
      $this->container->get('logger.factory'),
      $mockTime2
    );

    // Link again.
    $walletUserManager2->linkWalletToUser($walletAddress, (int) $user->id());

    $secondLastUsed = $database->select('wallet_auth_wallet_address', 'wa')
      ->fields('wa', ['last_used'])
      ->condition('wa.wallet_address', $walletAddress)
      ->execute()
      ->fetchField();

    // Verify timestamps reflect the mocked times.
    $this->assertEquals($initialTime, $firstLastUsed);
    $this->assertEquals($laterTime, $secondLastUsed);
    $this->assertGreaterThan($firstLastUsed, $secondLastUsed);
  }

  /**
   * Tests that wallet cannot be reassigned to different user.
   */
  public function testLinkWalletToDifferentUserRejected(): void {
    $walletAddress = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';

    // Create first user.
    $user1 = User::create([
      'name' => 'test_user_1',
      'mail' => 'test1@example.com',
      'status' => 1,
    ]);
    $user1->save();

    // Create second user.
    $user2 = User::create([
      'name' => 'test_user_2',
      'mail' => 'test2@example.com',
      'status' => 1,
    ]);
    $user2->save();

    // Link wallet to first user.
    $this->walletUserManager->linkWalletToUser($walletAddress, (int) $user1->id());

    // Try to link to second user - should be rejected.
    $this->walletUserManager->linkWalletToUser($walletAddress, (int) $user2->id());

    // Verify wallet is still linked to first user.
    $database = $this->container->get('database');
    $linkedUid = $database->select('wallet_auth_wallet_address', 'wa')
      ->fields('wa', ['uid'])
      ->condition('wa.wallet_address', $walletAddress)
      ->execute()
      ->fetchField();

    $this->assertEquals($user1->id(), $linkedUid);
    $this->assertNotEquals($user2->id(), $linkedUid);
  }

  /**
   * Tests linking multiple wallets to one user.
   */
  public function testLinkMultipleWalletsToOneUser(): void {
    $wallet1 = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';
    $wallet2 = '0x1234567890123456789012345678901234567890';

    $user = User::create([
      'name' => 'test_user',
      'mail' => 'test@example.com',
      'status' => 1,
    ]);
    $user->save();

    // Link both wallets to the same user.
    $this->walletUserManager->linkWalletToUser($wallet1, (int) $user->id());
    $this->walletUserManager->linkWalletToUser($wallet2, (int) $user->id());

    // Verify both are linked.
    $wallets = $this->walletUserManager->getUserWallets((int) $user->id());

    $this->assertCount(2, $wallets);
    $this->assertContains($wallet1, $wallets);
    $this->assertContains($wallet2, $wallets);
  }

  /**
   * Tests loading user by wallet address.
   */
  public function testLoadUserByWalletAddress(): void {
    $walletAddress = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';

    // Create user and link wallet.
    $user = $this->walletUserManager->createUserFromWallet($walletAddress);

    // Load user by wallet.
    $loadedUser = $this->walletUserManager->loadUserByWalletAddress($walletAddress);

    $this->assertNotNull($loadedUser);
    $this->assertEquals($user->id(), $loadedUser->id());
  }

  /**
   * Tests loading user by unknown wallet returns NULL.
   */
  public function testLoadUserByWalletAddressNotFound(): void {
    $unknownWallet = '0x0000000000000000000000000000000000000000';

    $loadedUser = $this->walletUserManager->loadUserByWalletAddress($unknownWallet);

    $this->assertNull($loadedUser);
  }

  /**
   * Tests loading user by inactive (blocked) user returns NULL.
   */
  public function testLoadUserByInactiveUser(): void {
    $walletAddress = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';

    // Create user and link wallet.
    $user = $this->walletUserManager->createUserFromWallet($walletAddress);

    // Block the user.
    $user->block();
    $user->save();

    // Try to load by wallet - should return NULL for inactive users.
    $loadedUser = $this->walletUserManager->loadUserByWalletAddress($walletAddress);

    $this->assertNull($loadedUser);
  }

  /**
   * Tests getting wallets for a user.
   */
  public function testGetUserWallets(): void {
    $wallet1 = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';
    $wallet2 = '0x1234567890123456789012345678901234567890';

    // Create user and link multiple wallets.
    $user = $this->walletUserManager->createUserFromWallet($wallet1);
    $this->walletUserManager->linkWalletToUser($wallet2, (int) $user->id());

    // Get all wallets for user.
    $wallets = $this->walletUserManager->getUserWallets((int) $user->id());

    $this->assertCount(2, $wallets);
    $this->assertContains($wallet1, $wallets);
    $this->assertContains($wallet2, $wallets);
  }

  /**
   * Tests login or create user with new user.
   */
  public function testLoginOrCreateUserNewUser(): void {
    $walletAddress = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';

    $user = $this->walletUserManager->loginOrCreateUser($walletAddress);

    $this->assertInstanceOf(User::class, $user);
    $this->assertStringStartsWith('wallet_', $user->getAccountName());
    $this->assertTrue($user->isActive());

    // Verify wallet is linked.
    $loadedUser = $this->walletUserManager->loadUserByWalletAddress($walletAddress);
    $this->assertNotNull($loadedUser);
    $this->assertEquals($user->id(), $loadedUser->id());
  }

  /**
   * Tests login or create user with existing user.
   */
  public function testLoginOrCreateUserExistingUser(): void {
    $walletAddress = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';

    // Create user first.
    $firstUser = $this->walletUserManager->loginOrCreateUser($walletAddress);
    $firstUid = $firstUser->id();

    // Login again with same wallet.
    $secondUser = $this->walletUserManager->loginOrCreateUser($walletAddress);

    // Should return the same user, not create a new one.
    $this->assertEquals($firstUid, $secondUser->id());
    $this->assertEquals($firstUser->getAccountName(), $secondUser->getAccountName());
  }

  /**
   * Tests that login updates last_used timestamp.
   */
  public function testLoginOrCreateUserUpdatesLastUsed(): void {
    $walletAddress = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';

    // Mock time service for first login (initial time).
    $initialTime = 1600000000;
    $mockTime = $this->createMock(TimeInterface::class);
    $mockTime->method('getRequestTime')->willReturn($initialTime);

    // Create a new instance with mocked time.
    $walletUserManager = new WalletUserManager(
      $this->container->get('database'),
      $this->container->get('externalauth.externalauth'),
      $this->container->get('entity_type.manager'),
      $this->container->get('logger.factory'),
      $mockTime
    );

    // Create user.
    $user = $walletUserManager->loginOrCreateUser($walletAddress);

    $database = $this->container->get('database');
    $firstLastUsed = $database->select('wallet_auth_wallet_address', 'wa')
      ->fields('wa', ['last_used'])
      ->condition('wa.wallet_address', $walletAddress)
      ->execute()
      ->fetchField();

    // Mock time service for second login (later time).
    $laterTime = $initialTime + 100;
    $mockTime2 = $this->createMock(TimeInterface::class);
    $mockTime2->method('getRequestTime')->willReturn($laterTime);

    // Create a new instance with new mocked time.
    $walletUserManager2 = new WalletUserManager(
      $this->container->get('database'),
      $this->container->get('externalauth.externalauth'),
      $this->container->get('entity_type.manager'),
      $this->container->get('logger.factory'),
      $mockTime2
    );

    // Login again.
    $user = $walletUserManager2->loginOrCreateUser($walletAddress);

    $secondLastUsed = $database->select('wallet_auth_wallet_address', 'wa')
      ->fields('wa', ['last_used'])
      ->condition('wa.wallet_address', $walletAddress)
      ->execute()
      ->fetchField();

    // Verify timestamps reflect the mocked times.
    $this->assertEquals($initialTime, $firstLastUsed);
    $this->assertEquals($laterTime, $secondLastUsed);
    $this->assertGreaterThan($firstLastUsed, $secondLastUsed);
  }

}

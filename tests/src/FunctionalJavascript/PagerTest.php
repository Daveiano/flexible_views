<?php

namespace Drupal\Tests\flexible_views\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Simple test to ensure that main page loads with module enabled.
 *
 * @group flexible_views
 */
class PagerTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'views_ui',
    'flexible_views',
    'flexible_views_test',
    'node',
  ];

  /**
   * The theme to use.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public static $testViews = [
    'test_flexible_views_pager',
    'test_flexible_views_pager_mini',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $account = $this->drupalCreateUser([
      'access content',
      'administer views',
    ]);

    $this->drupalLogin($account);

    // Create a content type.
    $this->drupalCreateContentType(['type' => 'flexible_views_test']);

    // Get the test data.
    $dataNodes = $this->provideTestDataContent();

    // Create test nodes.
    foreach ($dataNodes as $node) {
      $node = $this->drupalCreateNode([
        'type' => 'flexible_views_test',
        'title' => $node[0],
        'body' => [
          'value' => $node[1],
        ],
      ]);
      $node->save();
    }

    // Create the view.
    ViewTestData::createTestViews(static::class, ['flexible_views_test']);
  }

  /**
   * Data provider for setUp.
   *
   * @return array
   *   Nested array of testing data, Arranged like this:
   *   - Title
   *   - Body
   */
  protected function provideTestDataContent() {
    return [
      [
        'Node Content 1',
        'Node Content Body 1',
      ],
      [
        'Node Content 2',
        'Node Content Body 2',
      ],
      [
        'Node Content 3',
        'Node Content Body 3',
      ],
      [
        'Node Content 4',
        'Node Content Body 4',
      ],
      [
        'Node Content 5',
        'Node Content Body 5',
      ],
      [
        'Node Content 6',
        'Node Content Body 6',
      ],
      [
        'Node Content 7',
        'Node Content Body 7',
      ],
    ];
  }

  /**
   * Test pagination for mini pager.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testPagerMiniPageChange() {
    // Load the linked page display.
    $this->drupalGet('admin/test-flexible-views-pager-mini');
    $this->click('a[title="Go to next page"]');

    $this->checkThatFiltersAreNotVisible();
  }

  /**
   * Test pagination for full pager.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testPagerFullPageChange() {
    // Load the linked page display.
    $this->drupalGet('admin/test-flexible-views-pager');
    $this->click('a[title="Go to next page"]');

    $this->checkThatFiltersAreNotVisible();
  }

  /**
   * Checks that the not chosen filters are not visible.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function checkThatFiltersAreNotVisible() {
    // Check that the body filter is invisible.
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[contains(@class,'filter-wrap')]//input[@id='edit-body-value-check-deactivate']")->isVisible(),
      'Body filter checkbox is visible'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[contains(@class,'filter-wrap')]//select[@id='edit-body-value-op']")->isVisible(),
      'Body filter op is visible'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[contains(@class,'filter-wrap')]//input[@id='edit-body-value']")->isVisible(),
      'Body filter value is visible'
    );

    // Check that the type_1 filter is invisible.
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[contains(@class,'filter-wrap')]//input[@id='edit-type-1-check-deactivate']")->isVisible(),
      'Type_1 filter checkbox is visible'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[contains(@class,'filter-wrap')]//select[@id='edit-type-1-op']")->isVisible(),
      'Type_1 filter op is visible'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[contains(@class,'filter-wrap')]//select[@id='edit-type-1']")->isVisible(),
      'Type_1 filter value is visible'
    );
  }

}

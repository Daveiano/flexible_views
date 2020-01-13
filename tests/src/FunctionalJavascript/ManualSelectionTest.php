<?php

namespace Drupal\Tests\flexible_views\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests the manual_selection exposed form.
 *
 * @group views
 */
class ManualSelectionTest extends WebDriverTestBase {
  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
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
    'test_flexible_views',
    'test_flexible_views_without_exposed',
  ];

  /**
   * {@inheritdoc}
   *
   * @todo Create base class for this and extend from there.
   */
  protected function setUp() {
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
    ];
  }

  /**
   * Check the initial rendering of the exposed form.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testInitialManualSelection() {
    // Load the linked page display.
    $this->drupalGet('admin/test-flexible-views');

    // Verify that the page contains the select filter element.
    $this->assertSession()->pageTextContains(t('- Select a filter -'));

    // Check that the title filter is visible.
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap always-visible']/span[@class='label']")->isVisible();
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap always-visible']//select[@id='edit-title-op']")->isVisible();
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap always-visible']//input[@id='edit-title']")->isVisible();

    // Check that the body filter is invisible.
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap']//input[@id='edit-body-value-check-deactivate']")->isVisible(),
      'Body filter checkbox is invisble'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap']//select[@id='edit-body-value-op']")->isVisible(),
      'Body filter op is invisble'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap']//input[@id='edit-body-value']")->isVisible(),
      'Body filter value is invisble'
    );

    // Check that the type_1 filter is invisible.
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap']//input[@id='edit-type-1-check-deactivate']")->isVisible(),
      'Type_1 filter checkbox is invisble'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap']//select[@id='edit-type-1-op']")->isVisible(),
      'Type_1 filter op is invisble'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap']//select[@id='edit-type-1']")->isVisible(),
      'Type_1 filter value is invisble'
    );
  }

  /**
   * Tests adding a filter.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testManualSelectionFilterAdd() {
    // Load the linked page display.
    $this->drupalGet('admin/test-flexible-views');

    $manual_select = $this->xpath("//select[@id='edit-manual-select-filter']");
    $manual_select[0]->selectOption('body_value');
    $manual_select[0]->blur();

    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap active']//input[@id='edit-body-value-check-deactivate']")->isVisible();
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap active']//select[@id='edit-body-value-op']")->isVisible();
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap active']//input[@id='edit-body-value']")->isVisible();
  }

  /**
   * Tests removing a filter.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testManualSelectionFilterRemove() {
    $this->testManualSelectionFilterAdd();

    // Uncheck the deactivate checkbox.
    $deactivate_checkbox = $this->xpath("//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap']//input[@id='edit-body-value-check-deactivate']");
    $deactivate_checkbox[0]->uncheck();

    // Check that the body filter is invisible again.
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap']//input[@id='edit-body-value-check-deactivate']")->isVisible(),
      'Body filter checkbox is invisble'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap']//select[@id='edit-body-value-op']")->isVisible(),
      'Body filter op is invisble'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap']//input[@id='edit-body-value']")->isVisible(),
      'Body filter value is invisble'
    );
  }

  /**
   * Tests that the manual_selection is not shown if a view has nothing exposed.
   */
  public function testViewWithoutExposedFilters() {
    // Load the linked page display.
    $this->drupalGet('admin/test-flexible-views-2');

    // Check that the select exists.
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//select[@id='edit-manual-select-filter']");

    // Check that the select is not visible.
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//select[@id='edit-manual-select-filter']")->isVisible(),
      'Manual select is visible.'
    );
  }

}

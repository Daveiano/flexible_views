<?php

namespace Drupal\Tests\flexible_views\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests the manual_selection exposed form.
 *
 * @group flexible_views
 */
class ManualSelectionTest extends WebDriverTestBase {
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
    'test_flexible_views',
    'test_flexible_views_without_exposed',
  ];

  /**
   * {@inheritdoc}
   *
   * @todo Create base class for this and extend from there.
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
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap always-visible']/div/select[@id='edit-title-op']")->isVisible();
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap always-visible']/div/input[@id='edit-title']")->isVisible();

    // Check that the type filter is visible.
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap always-visible']/div/select[@id='edit-type-1-op']")->isVisible();
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap always-visible']/div/select[@id='edit-type-1']")->isVisible();

    $always_visible_labels = $this->xpath("//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap always-visible']/span[@class='label']");
    $this->assertEquals(2, count($always_visible_labels), 'Incorrect always visible options count.');

    // Check that the body filter is invisible.
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap']/div/input[@id='edit-body-value-check-deactivate']")->isVisible(),
      'Body filter checkbox is invisble'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap']/div/select[@id='edit-body-value-op']")->isVisible(),
      'Body filter op is invisble'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap']/div/input[@id='edit-body-value']")->isVisible(),
      'Body filter value is invisble'
    );

    // Check that the uuid filter is invisible.
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap']/div/input[@id='edit-uuid-check-deactivate']")->isVisible(),
      'Type_1 filter checkbox is invisble'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap']/div/input[@id='edit-uuid']")->isVisible(),
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

    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap active']/div/input[@id='edit-body-value-check-deactivate']")->isVisible();
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap active']/div/select[@id='edit-body-value-op']")->isVisible();
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap active']/div/input[@id='edit-body-value']")->isVisible();
  }

  /**
   * Checks visibility of used filters after submit.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testFilterVisibilityAfterSubmit() {
    $this->testManualSelectionFilterAdd();

    // Set body filter op to contains.
    $body_filter_op = $this->xpath("//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap active']//select[@id='edit-body-value-op']");
    $body_filter_op[0]->selectOption('contains');
    $body_filter_op[0]->blur();

    // Set body filter value.
    $body_filter = $this->xpath("//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap active']//input[@id='edit-body-value']");
    $body_filter[0]->setValue("Body 2");
    $body_filter[0]->blur();

    // Submit form.
    $this->submitForm([], 'Apply');

    // Check that the body filter is still visible.
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap active']/div/input[@id='edit-body-value-check-deactivate']")->isVisible();
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap active']/div/select[@id='edit-body-value-op']")->isVisible();
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap active']/div/input[@id='edit-body-value']")->isVisible();

    // Check the result.
    $this->assertSession()->pageTextNotContains(t('Node Content 4'));
    $this->assertSession()->pageTextContains(t('Node Content 2'));
  }

  /**
   * Tests removing a filter.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testManualSelectionFilterRemove() {
    $this->testManualSelectionFilterAdd();

    // Uncheck the deactivate checkbox.
    $deactivate_checkbox = $this->xpath("//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap active']//input[@id='edit-body-value-check-deactivate']");
    $deactivate_checkbox[0]->uncheck();

    // Check that the body filter is invisible again.
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap']/div/input[@id='edit-body-value-check-deactivate']")->isVisible(),
      'Body filter checkbox is invisble'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap']/div/select[@id='edit-body-value-op']")->isVisible(),
      'Body filter op is invisble'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']/div[@class='filter-wrap']/div/input[@id='edit-body-value']")->isVisible(),
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

    // Check select options count.
    $manual_select_options = $this->xpath("//select[@id='edit-manual-select-filter']/option");
    $this->assertEquals(1, count($manual_select_options), 'Incorrect manual select options count.');
  }

  /**
   * Checks visibility of filters after change sorting.
   */
  public function testFilterVisibilityAfterSorting() {
    // Load the linked page display.
    $this->drupalGet('admin/test-flexible-views');

    // Change the sorting.
    $content_type_header = $this->xpath("//th[@id='view-type-table-column']/a");
    $content_type_header[0]->click();

    // Check that the e.g. body filter is invisible.
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//input[@id='edit-body-value-check-deactivate']")->isVisible(),
      'Body filter value is visible'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//select[@id='edit-body-value-op']")->isVisible(),
      'Body filter op is visible'
    );
    $this->assertFalse(
      $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//input[@id='edit-body-value']")->isVisible(),
      'Body filter value is visible'
    );
  }

}

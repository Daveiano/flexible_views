<?php

namespace Drupal\Tests\flexible_views\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Simple test to ensure that main page loads with module enabled.
 *
 * @group flexible_views
 */
class FlexibleViewsTest extends BrowserTestBase {

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
  public static $testViews = ['test_flexible_views'];

  /**
   * {@inheritdoc}
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
   * Test proper response of the view.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testFrontendPageHttpResponse() {
    // Load the linked page display.
    $this->drupalGet('admin/test-flexible-views');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Test proper response of the views ui config page.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testBackendConfigHttpResponse() {
    // Load the linked page display.
    $this->drupalGet('admin/structure/views/view/test_flexible_views/edit/page_1');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Check the flexible_table column visibility.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testFlexibleTable() {
    // Load the linked page display.
    $this->drupalGet('admin/test-flexible-views');

    // Verify that the page contains generated content.
    $this->assertSession()->pageTextContains(t('Node Content 4'));
    $this->assertSession()->pageTextContains('flexible_views_test');

    // Verify that the page does not contain generated content from the
    // initially hidden columns.
    $this->assertSession()->pageTextNotContains(t('Node Content Body 4'));
  }

  /**
   * Check that the column_selector is present.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testColumnSelector() {
    // Load the linked page display.
    $this->drupalGet('admin/test-flexible-views');

    // Verify that the page contains the column options content.
    $this->assertSession()->pageTextContains(t('Column Options'));

    // Check the available select.
    $available_select = $this->xpath("//*[@id='edit-flexible-tables-fieldset']//select[@id='flexible-table-available-columns']");
    $this->assertEqual(count($available_select), 1, 'Available select form element is visible.');

    // Check for the right options in the available select.
    $available_select_options = $this->xpath("//*[@id='edit-flexible-tables-fieldset']//select[@id='flexible-table-available-columns']/option");
    $this->assertEqual(count($available_select_options), 1, 'Correct available select option count.');
    $available_select_option = $this->xpath("//*[@id='edit-flexible-tables-fieldset']//select[@id='flexible-table-available-columns']/option[@value='body']");
    $this->assertEqual(count($available_select_option), 1, 'Correct available select option is visible.');

    // Check the selected select.
    $selected_select = $this->xpath("//*[@id='edit-flexible-tables-fieldset']//select[@id='flexible-table-selected-columns']");
    $this->assertEqual(count($selected_select), 1, 'Selected select form element is visible.');

    // Check for the right options in the selected select.
    $selected_select_options = $this->xpath("//*[@id='edit-flexible-tables-fieldset']//select[@id='flexible-table-selected-columns']/option");
    $this->assertEqual(count($selected_select_options), 2, 'Correct selected select option count.');
    $selected_select_option1 = $this->xpath("//*[@id='edit-flexible-tables-fieldset']//select[@id='flexible-table-selected-columns']/option[@value='title']");
    $this->assertEqual(count($selected_select_option1), 1, 'Correct selected select option is visible.');
    $selected_select_option2 = $this->xpath("//*[@id='edit-flexible-tables-fieldset']//select[@id='flexible-table-selected-columns']/option[@value='type']");
    $this->assertEqual(count($selected_select_option2), 1, 'Correct selected select option is visible.');

    // Check for the buttons.
    $this->assertSession()->elementExists('xpath', "//div[@class='form-item move-buttons']/div[@class='move-right']");
    $this->assertSession()->elementExists('xpath', "//div[@class='form-item move-buttons']/div[@class='move-left']");
    $this->assertSession()->elementExists('xpath', "//div[@class='form-item move-buttons']/div[@class='move-top']");
    $this->assertSession()->elementExists('xpath', "//div[@class='form-item move-buttons']/div[@class='move-down']");
  }

  /**
   * Tests column selector form submit via parameters.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testColumnSelectorFormSubmit() {
    // Load the linked page display.
    $this->drupalGet('admin/test-flexible-views');

    $this->assertSession()->pageTextContains(t('Node Content 4'));
    $this->assertSession()->pageTextNotContains(t('Node Content Body 4'));

    // Submit the form (add the body column to the selected columns).
    $this->submitForm([
      "selected_columns_submit_order" => Json::encode(["body", "type"]),
    ], 'Apply');

    $this->assertSession()->pageTextNotContains(t('Node Content 4'));
    $this->assertSession()->pageTextContains(t('Node Content Body 4'));
  }

  /**
   * Check the initial display and presence of needed elements.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testManualSelection() {
    // Load the linked page display.
    $this->drupalGet('admin/test-flexible-views');

    // Verify that the page contains the select filter element.
    $this->assertSession()->pageTextContains(t('- Select a filter -'));
    $this->assertSession()->elementExists('xpath', "//select[@id='edit-manual-select-filter']");

    // Verify that the select contains the right options.
    $manual_select_options = $this->xpath("//select[@id='edit-manual-select-filter']/option");
    $this->assertEqual(count($manual_select_options), 3, 'Correct manual select options count.');
    $this->assertSession()->elementExists('xpath', "//select[@id='edit-manual-select-filter']/option[@value='body_value']");
    $this->assertSession()->elementExists('xpath', "//select[@id='edit-manual-select-filter']/option[@value='type_1']");

    // Check that the title filter is present.
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap always-visible']/span[@class='label']");
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap always-visible']//select[@id='edit-title-op']");
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap always-visible']//input[@id='edit-title']");

    // Check that the body filter is present.
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap']//input[@id='edit-body-value-check-deactivate']");
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap']//select[@id='edit-body-value-op']");
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap']//input[@id='edit-body-value']");

    // Check that the type_1 filter is present.
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap']//input[@id='edit-type-1-check-deactivate']");
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap']//select[@id='edit-type-1-op']");
    $this->assertSession()->elementExists('xpath', "//form[@class='views-exposed-form manual-selection-form']//div[@class='filter-wrap']//select[@id='edit-type-1']");
  }

}

<?php

namespace Drupal\Tests\flexible_views\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests the column_selector exposed filter.
 *
 * @group flexible_views
 */
class ColumnSelectorTest extends WebDriverTestBase {
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
   * Tests column add functionality.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testColumnAdd() {
    // Load the linked page display.
    $this->drupalGet('admin/test-flexible-views');

    // Verify the number of columns.
    // TODO: Write better selector.
    $columns = $this->xpath("//table/thead/tr/th");
    $this->assertEqual(count($columns), 2, 'Wrong column count');

    $this->assertSession()->pageTextNotContains(t('Node Content Body 4'));

    $details_wrapper = $this->xpath("//details[@id='edit-flexible-tables-fieldset']/summary");
    $details_wrapper[0]->click();

    $available_select = $this->xpath("//select[@id='flexible-table-available-columns']");
    $available_select[0]->selectOption('body');
    $available_select[0]->blur();

    $move_right_button = $this->xpath("//div[@class='form-item move-buttons']/div[@class='move-right']");
    $move_right_button[0]->click();

    $this->submitForm([], 'Apply');

    // Verify the number of columns.
    // TODO: Write better selector.
    $columns = $this->xpath("//table/thead/tr/th");
    $this->assertEqual(count($columns), 3, 'Wrong column count');

    $this->assertSession()->pageTextContains(t('Node Content Body 4'));
  }

  /**
   * Test column remove functionality.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testColumnRemove() {
    $this->testColumnAdd();

    $details_wrapper = $this->xpath("//details[@id='edit-flexible-tables-fieldset']/summary");
    $details_wrapper[0]->click();

    $selected_select = $this->xpath("//select[@id='flexible-table-selected-columns']");
    $selected_select[0]->selectOption('body');
    $selected_select[0]->blur();

    $move_right_button = $this->xpath("//div[@class='form-item move-buttons']/div[@class='move-left']");
    $move_right_button[0]->click();

    $this->submitForm([], 'Apply');

    // TODO: Write better selector.
    $columns = $this->xpath("//table/thead/tr/th");
    $this->assertEqual(count($columns), 2, 'Wrong column count');

    $this->assertSession()->pageTextNotContains(t('Node Content Body 4'));
  }

  /**
   * Tests correct column sorting.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testColumnSort() {
    $this->testColumnAdd();

    // Verify correct sort order.
    $columns = $this->xpath("//table/thead/tr/th");
    $this->assertTrue($columns[0]->hasClass('views-field-body'), 'Wrong column sorting, Column 1');

    $columns = $this->xpath("//table/thead/tr/th");
    $this->assertTrue($columns[1]->hasClass('views-field-title'), 'Wrong column sorting, Column 2');

    $columns = $this->xpath("//table/thead/tr/th");
    $this->assertTrue($columns[2]->hasClass('views-field-type'), 'Wrong column sorting, Column 3');

    // Change the sorting.
    $details_wrapper = $this->xpath("//details[@id='edit-flexible-tables-fieldset']/summary");
    $details_wrapper[0]->click();

    $selected_select = $this->xpath("//select[@id='flexible-table-selected-columns']");
    $selected_select[0]->selectOption('body');

    $move_down_button = $this->xpath("//div[@class='form-item move-buttons']/div[@class='move-down']");
    $move_down_button[0]->click();
    $move_down_button[0]->click();

    $selected_select[0]->selectOption('type');

    $move_up_button = $this->xpath("//div[@class='form-item move-buttons']/div[@class='move-top']");
    $move_up_button[0]->click();

    $this->submitForm([], 'Apply');

    // Verify correct sort order.
    $columns = $this->xpath("//table/thead/tr/th");
    $this->assertTrue($columns[0]->hasClass('views-field-type'), 'Wrong column sorting, Column 1');

    $columns = $this->xpath("//table/thead/tr/th");
    $this->assertTrue($columns[1]->hasClass('views-field-title'), 'Wrong column sorting, Column 2');

    $columns = $this->xpath("//table/thead/tr/th");
    $this->assertTrue($columns[2]->hasClass('views-field-body'), 'Wrong column sorting, Column 3');
  }

}

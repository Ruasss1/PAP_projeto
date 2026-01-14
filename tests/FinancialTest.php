<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/functions.php';

class FinancialTest extends TestCase
{
    private $productId;

    protected function setUp(): void
    {
        // create a product for testing (use valid ENUM category value)
        $data = ['name' => 'TEST_PRODUCT_' . uniqid(), 'category' => 'Mercearia', 'cost_price' => 1.00, 'sell_price' => 2.50, 'stock' => 100];
        $this->productId = add_product($data);
    }

    protected function tearDown(): void
    {
        // cleanup product and related data
        $pdo = db_connect();
        // remove sale items for this product
        $pdo->prepare('DELETE FROM sale_items WHERE product_id = ?')->execute([$this->productId]);
        // (intentionally not removing orphan sales here — keep cleanup minimal to avoid FK errors)
        // remove breaks and the product
        $pdo->prepare('DELETE FROM breaks WHERE product_id = ?')->execute([$this->productId]);
        delete_product($this->productId);
    }

    public function testAddSaleReducesStockAndRecordsTransaction()
    {
        $before = get_product($this->productId)['stock'];
        // add_sale expects an array of items
        $items = [
            ['product_id' => $this->productId, 'quantity' => 2]
        ];
        $res = add_sale($items, 'Dinheiro');
        $this->assertTrue(is_numeric($res), 'add_sale should return sale ID');
        $after = get_product($this->productId)['stock'];
        $this->assertEquals($before - 2, $after, 'Stock must decrease by qty sold');

        $pdo = db_connect();
        $r = $pdo->query('SELECT COUNT(*) FROM sale_items WHERE product_id = ' . (int)$this->productId)->fetchColumn();
        $this->assertGreaterThanOrEqual(1, $r, 'A sale item record should exist');
    }

    public function testRecordBreakReducesStockAndRecordsBreak()
    {
        $before = get_product($this->productId)['stock'];
        $res = record_break($this->productId, 3, 'TestBreak');
        $this->assertTrue(is_numeric($res), 'record_break should return break ID');
        $after = get_product($this->productId)['stock'];
        $this->assertEquals($before - 3, $after, 'Stock must decrease by qty broken');

        $pdo = db_connect();
        $r = $pdo->query('SELECT COUNT(*) FROM breaks WHERE product_id = ' . (int)$this->productId)->fetchColumn();
        $this->assertGreaterThanOrEqual(1, $r, 'A break record should exist');
    }

    public function testFinancialSummaryReflectsSalesAndBreaks()
    {
        // make a sale and a break and check summary numbers
        $items = [
            ['product_id' => $this->productId, 'quantity' => 1]
        ];
        add_sale($items, 'Dinheiro');
        record_break($this->productId, 1, 'TestBreak2');

        $summary = get_financial_summary();
        $this->assertArrayHasKey('revenue', $summary);
        $this->assertArrayHasKey('cogs', $summary);
        $this->assertArrayHasKey('breaks', $summary);
        $this->assertIsNumeric($summary['revenue']);
        $this->assertIsNumeric($summary['cogs']);
        $this->assertIsNumeric($summary['breaks']);
    }
}

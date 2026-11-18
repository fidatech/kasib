<?php

namespace Tests\Feature;

use App\Models\AccountBalance;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Party;
use App\Services\Accounts;
use App\Services\Trade;
use App\Services\Vouchers;
use App\Support\Menu;
use App\Support\Num;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Version 2, the Pro plan: items, sales, purchases and their returns. */
class TradeTest extends TestCase
{
    use RefreshDatabase;

    private function cash(string $code = 'AFN'): \App\Models\Account
    {
        return Accounts::cash(Branch::main(), Currency::where('code', $code)->firstOrFail());
    }

    private function party(string $name, string $group): Party
    {
        return Party::create(['account_group_id' => AccountGroup::byKey($group)->id, 'name' => $name])->fresh();
    }

    private function owed(Party $party): string
    {
        return Vouchers::balances($party->fresh())[0]['amount'] ?? '0.00';
    }

    private function invoice(string $kind, Party $party, Item $item, string $qty, string $price, string $paid = '0', array $more = [])
    {
        return $this->post('/trade/'.$kind, ['date' => today_iso(), 'party_id' => $party->id, 'currency_id' => Currency::base()->id,
            'lines' => [['item_id' => $item->id, 'qty' => $qty, 'price' => $price], ['item_id' => '', 'qty' => '', 'price' => '']],
            'paid' => $paid, 'account_id' => $this->cash()->id] + $more);
    }

    public function test_sales_and_purchases_belong_to_the_pro_plan(): void
    {
        $tenant = $this->business();
        $owner = $this->owner();
        $this->get('/items')->assertForbidden()->assertSee('This page is part of the Pro plan');
        $this->get('/trade/sales')->assertForbidden();
        $this->assertNull(collect(Menu::for($owner))->firstWhere('label', 'Sales & Purchases'));
        $this->get('/service/licence')->assertOk()->assertSee('Ask for the Pro plan');
        $this->get('/money-in')->assertOk();

        $tenant->update(['plan' => 'pro']);
        $this->enter($tenant->fresh());
        $this->actingAs($owner);
        $this->get('/items')->assertOk();
        $this->get('/trade/sales')->assertOk();
        $this->assertNotNull(collect(Menu::for($owner))->firstWhere('label', 'Sales & Purchases'));
    }

    public function test_goods_are_bought_sold_returned_and_counted_and_the_books_stay_balanced(): void
    {
        $this->business(['plan' => 'pro']);
        $this->owner();
        $this->openingCash('AFN', '100000');
        $supplier = $this->party('Haji Wholesale', 'suppliers');
        $ahmad = $this->party('Ahmad', 'customers');
        $walkIn = Trade::walkIn();

        $this->post('/items', ['name' => 'Rice', 'code' => 'R-1', 'unit' => 'kg', 'sale_price' => '150', 'cost_price' => '100', 'reorder_level' => '20', 'track_stock' => 1, 'is_active' => 1])->assertSessionHasNoErrors();
        $rice = Item::firstOrFail();
        $this->get('/trade/purchases/create')->assertOk()->assertSee('Rice');
        $this->get('/trade/sales/create')->assertOk()->assertSee('Cash customer');

        // bought: 100 kg at 100, 4,000 paid now; then 100 kg at 120 on credit. The average becomes 110.
        $this->invoice('purchases', $supplier, $rice, '100', '100', '4000')->assertSessionHasNoErrors();
        $this->invoice('purchases', $supplier, $rice, '100', '120')->assertSessionHasNoErrors();
        $rice->refresh();
        $this->assertSame(['200.000', '110.0000'], [Num::round($rice->stock, 3), Num::round($rice->avg_cost, 4)]);
        $this->assertSame('18000.00', $this->owed($supplier));
        $this->assertSame('96000.00', Num::money($this->cash()->fresh()->total->balance));

        // sold over the counter: 50 kg at 150, paid in full; and 10 kg to Ahmad on credit
        $this->invoice('sales', $walkIn, $rice, '50', '150', '7500')->assertSessionHasNoErrors();
        $this->invoice('sales', $ahmad, $rice, '10', '150')->assertSessionHasNoErrors();
        $sale = Invoice::where('kind', 'sale')->orderBy('id')->firstOrFail();
        $this->assertSame(['SI-000001', '7500.00', '5500.00'], [$sale->number, Num::money($sale->total), Num::money($sale->cost_total)]);
        $this->assertNotNull($sale->payment_voucher_id);
        $this->assertSame('-1500.00', $this->owed($ahmad));
        $this->assertSame('140.000', Num::round($rice->fresh()->stock, 3));

        // refused: more than there is, a cash customer who does not pay, more paid than the total
        $this->invoice('sales', $ahmad, $rice, '1000', '150');
        $this->invoice('sales', $walkIn, $rice, '1', '150', '0');
        $this->invoice('sales', $ahmad, $rice, '1', '150', '500');
        $this->assertSame(2, Invoice::where('kind', 'sale')->count());

        // the reports: 9,000 sold, 6,600 of cost, 2,400 earned; 140 kg worth 15,400 in stock
        $this->get('/reports/sales-by-item')->assertOk()->assertSee('Rice')->assertSee('9,000.00')->assertSee('6,600.00')->assertSee('2,400.00');
        $this->get('/reports/stock')->assertOk()->assertSee('140 kg')->assertSee('15,400.00');
        $this->get('/reports/sales-by-customer')->assertOk()->assertSee('Ahmad')->assertSee('1,500.00');
        $this->get('/reports/sales-by-customer?side=purchases')->assertOk()->assertSee('Haji Wholesale')->assertSee('22,000.00');
        $this->get('/reports/profit-and-loss')->assertOk()->assertSee('9,000.00')->assertSee('6,600.00');
        $this->get('/trade/sales')->assertOk()->assertSee('SI-000001');
        $this->get('/trade/sales/'.$sale->id)->assertOk()->assertSee('Rice')->assertSee('7,500.00');
        $this->get('/trade/sales/'.$sale->id.'?print=1')->assertOk();
        $this->get('/items/'.$rice->id.'/stock')->assertOk()->assertSee('SI-000001')->assertSee('PI-000001');

        // Ahmad brings 2 kg back; 20 kg go back to the supplier
        $this->invoice('sale-returns', $ahmad, $rice, '2', '150')->assertSessionHasNoErrors();
        $this->assertSame('-1200.00', $this->owed($ahmad));
        $this->invoice('purchase-returns', $supplier, $rice, '20', '120')->assertSessionHasNoErrors();
        $this->assertSame('15600.00', $this->owed($supplier));
        $this->assertSame('122.000', Num::round($rice->fresh()->stock, 3));

        // the sale over the counter is cancelled: the goods and the money go back
        $this->delete('/trade/sales/'.$sale->id, ['reason' => 'Wrong customer'])->assertSessionHas('success');
        $this->assertTrue($sale->fresh()->isCancelled());
        $this->assertSame('172.000', Num::round($rice->fresh()->stock, 3));
        $this->assertSame('96000.00', Num::money($this->cash()->fresh()->total->balance));

        // a count finds 170: two kilos leave as cost of goods; an item on invoices is not deleted
        $this->post('/items/'.$rice->id.'/stock', ['counted' => '170', 'note' => 'Spilled'])->assertSessionHas('success');
        $this->assertSame('170.000', Num::round($rice->fresh()->stock, 3));
        $this->delete('/items/'.$rice->id);
        $this->assertNotNull(Item::find($rice->id));
        $this->get('/items?show=low')->assertOk()->assertDontSee('R-1');

        $this->assertSame(0.0, round((float) AccountBalance::sum('base_balance'), 2), 'Debits and credits of the whole ledger must be equal.');
        // what the books say the stock is worth is what the items say
        $this->assertSame(Num::money(Num::mul('170', '110')), Num::money(Trade::account('inventory')->fresh()->total->balance));
    }

    public function test_a_purchase_in_dollars_enters_the_stock_at_its_value_in_the_base_currency(): void
    {
        $this->business(['plan' => 'pro']);
        $this->owner();
        $this->rates(['USD' => '70']);
        $supplier = $this->party('Dubai Trading', 'suppliers');
        $phone = Item::create(['name' => 'Phone', 'unit' => 'pcs', 'sale_price' => '9000', 'cost_price' => '7000']);
        $service = Item::create(['name' => 'Delivery', 'unit' => 'trip', 'track_stock' => false]);
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $this->post('/trade/purchases', ['date' => today_iso(), 'party_id' => $supplier->id, 'currency_id' => $usd->id, 'discount' => '10',
            'lines' => [['item_id' => $phone->id, 'qty' => '10', 'price' => '100'], ['item_id' => $service->id, 'qty' => '1', 'price' => '10']]])->assertSessionHasNoErrors();
        $invoice = Invoice::sole();
        // 1,010 less 10 of discount is 1,000 dollars, 70,000 afghani; the phones carry their share of it
        $this->assertSame(['1000.00', '70000.00'], [Num::money($invoice->total), Num::money($invoice->base_total)]);
        $this->assertSame('10.000', Num::round($phone->fresh()->stock, 3));
        $this->assertSame(Num::money($invoice->lines->firstWhere('item_id', $phone->id)->base_amount), Num::money(Num::mul('10', $phone->fresh()->avg_cost)));
        $this->assertSame('0.000', Num::round($service->fresh()->stock, 3));
        $this->assertSame('1000.00', $this->owed($supplier));
        $this->assertSame(0.0, round((float) AccountBalance::sum('base_balance'), 2));
    }

    public function test_units_are_a_list_of_their_own(): void
    {
        $this->business(['plan' => 'pro']);
        $this->owner();
        $this->get('/units')->assertOk()->assertSee('carton')->assertSee('kg');

        // a unit of the business is added, and items are counted in it
        $this->post('/units', ['name' => 'sack', 'name_local' => 'جوال'])->assertSessionHasNoErrors();
        $this->post('/units', ['name' => 'sack'])->assertSessionHasErrors('name');
        $this->get('/items/create')->assertOk()->assertSee('sack');
        $form = ['name' => 'Potatoes', 'sale_price' => '900', 'cost_price' => '800', 'track_stock' => '1', 'is_active' => '1'];
        $this->post('/items', $form + ['unit' => 'handful'])->assertSessionHasErrors('unit');
        $this->post('/items', $form + ['unit' => 'sack'])->assertSessionHasNoErrors();

        // renamed, its items follow; in use, it is not deleted; unused, it goes
        $unit = \App\Models\Unit::where('name', 'sack')->sole();
        $this->put('/units/'.$unit->id, ['name' => 'jawal', 'name_local' => 'جوال'])->assertSessionHasNoErrors();
        $this->assertSame('jawal', \App\Models\Item::where('name', 'Potatoes')->value('unit'));
        $this->delete('/units/'.$unit->id);
        $this->assertSame(1, \App\Models\Unit::where('name', 'jawal')->count());
        $this->delete('/units/'.\App\Models\Unit::where('name', 'dozen')->value('id'));
        $this->assertSame(0, \App\Models\Unit::where('name', 'dozen')->count());
    }

    public function test_the_costs_of_a_purchase_go_into_the_cost_of_the_goods(): void
    {
        $this->business(['plan' => 'pro']);
        $this->owner();
        $main = \App\Models\Branch::main();
        $afn = \App\Models\Currency::base();
        $cash = \App\Services\Accounts::cash($main, $afn);
        $funder = \App\Models\Party::create(['account_group_id' => \App\Models\AccountGroup::byKey('customers')->id, 'name' => 'Funder'])->fresh();
        \App\Services\Vouchers::moneyIn($funder, $cash, today_iso(), '500000');
        $supplier = \App\Models\Party::create(['account_group_id' => \App\Models\AccountGroup::byKey('suppliers')->id, 'name' => 'Importer'])->fresh();
        $rice = Item::create(['name' => 'Rice', 'unit' => 'bag', 'sale_price' => '3000', 'cost_price' => '2000']);
        $oil = Item::create(['name' => 'Oil', 'unit' => 'can', 'sale_price' => '1500', 'cost_price' => '1000']);

        // 100 bags at 2,000 and 100 cans at 1,000; customs 24,000 and transport 6,000 are paid in cash
        $this->get('/trade/purchases/create')->assertOk()->assertSee('Costs of this purchase')->assertSee('Customs duty');
        $this->get('/trade/sales/create')->assertOk()->assertDontSee('Costs of this purchase');
        $form = ['date' => today_iso(), 'party_id' => $supplier->id, 'currency_id' => $afn->id,
            'lines' => [['item_id' => $rice->id, 'qty' => '100', 'price' => '2000'], ['item_id' => $oil->id, 'qty' => '100', 'price' => '1000']]];
        // a cost without its account is refused
        $this->post('/trade/purchases', $form + ['costs' => [['name' => 'Customs duty', 'amount' => '24000']]])->assertSessionHas('danger');
        $this->assertSame(0, \App\Models\Invoice::count());
        $this->post('/trade/purchases', $form + ['costs' => [['name' => 'Customs duty', 'amount' => '24000', 'account_id' => $cash->id], ['name' => 'Transport', 'amount' => '6000', 'account_id' => $cash->id], ['name' => '', 'amount' => '']]])->assertSessionHasNoErrors()->assertSessionMissing('danger');
        $invoice = \App\Models\Invoice::sole();

        // the costs are shared by value: rice carries 20,000 of them and oil 10,000, so one bag costs 2,200 and one can 1,100
        $this->assertSame(['30000.00', '330000.00'], [Num::money($invoice->costs_total), Num::money($invoice->cost_total)]);
        $this->assertSame(['2200.0000', '1100.0000'], [Num::round(Item::find($rice->id)->avg_cost, 4), Num::round(Item::find($oil->id)->avg_cost, 4)]);
        // the supplier is owed what the goods cost, not the costs; those left the cash box; the stock is worth both
        $this->assertSame(['300000.00', '470000.00', '330000.00'], [Num::money(Num::neg(\App\Services\Ledger::balance(\App\Services\Accounts::party($supplier, $afn, $main)))), Num::money(\App\Services\Ledger::balance($cash)), Num::money(\App\Services\Ledger::balance(\App\Services\Trade::account('inventory')))]);
        $this->get('/trade/purchases/'.$invoice->id)->assertOk()->assertSee('Customs duty')->assertSee('Cost of the goods after the costs')->assertSee('2,200.00');

        // a sale takes the goods out at the cost after the costs
        \App\Services\Trade::record(\App\Models\Invoice::SALE, $funder, $afn, today_iso(), [['item_id' => $rice->id, 'qty' => '10', 'price' => '3000']]);
        $this->assertSame('22000.00', Num::money(\App\Models\Invoice::where('kind', 'sale')->sole()->cost_total));

        // cancelled, the costs come back to the cash box and the average is what it was without the purchase
        \App\Services\Trade::cancel(\App\Models\Invoice::where('kind', 'sale')->sole());
        \App\Services\Trade::cancel($invoice);
        $this->assertSame(['500000.00', '0.000'], [Num::money(\App\Services\Ledger::balance($cash)), Num::round(Item::find($rice->id)->stock, 3)]);
    }

    public function test_goods_are_kept_in_warehouses_and_moved_between_them(): void
    {
        $this->business(['plan' => 'pro']);
        $this->owner();
        $main = \App\Models\Branch::main();
        $afn = \App\Models\Currency::base();
        $supplier = \App\Models\Party::create(['account_group_id' => \App\Models\AccountGroup::byKey('suppliers')->id, 'name' => 'Importer'])->fresh();
        $customer = \App\Models\Party::create(['account_group_id' => \App\Models\AccountGroup::byKey('customers')->id, 'name' => 'Shopkeeper'])->fresh();
        $rice = Item::create(['name' => 'Rice', 'unit' => 'bag', 'sale_price' => '3000', 'cost_price' => '2000']);

        // every branch starts with its main warehouse; a second one is added
        $this->get('/warehouses')->assertOk()->assertSee('Main warehouse');
        $this->post('/warehouses', ['name' => 'Store room', 'is_active' => '1'])->assertSessionHasNoErrors();
        $first = \App\Models\Warehouse::main($main);
        $store = \App\Models\Warehouse::where('name', 'Store room')->sole();
        $this->get('/trade/purchases/create')->assertOk()->assertSee('Into the warehouse');

        // 100 bags at 2,000 arrive in the main warehouse, 50 at 2,600 in the store room
        $form = ['date' => today_iso(), 'party_id' => $supplier->id, 'currency_id' => $afn->id];
        $this->post('/trade/purchases', $form + ['lines' => [['item_id' => $rice->id, 'qty' => '100', 'price' => '2000']]])->assertSessionMissing('danger');
        $this->post('/trade/purchases', $form + ['warehouse_id' => $store->id, 'lines' => [['item_id' => $rice->id, 'qty' => '50', 'price' => '2600']]])->assertSessionMissing('danger');
        $held = fn ($warehouse) => \App\Models\ItemStock::where('item_id', $rice->id)->where('warehouse_id', $warehouse->id)->first();
        $this->assertSame(['100.000', '50.000', '150.000', '2200.0000'], [Num::round($held($first)->stock, 3), Num::round($held($store)->stock, 3), Num::round(Item::find($rice->id)->stock, 3), Num::round(Item::find($rice->id)->avg_cost, 4)]);

        // a sale cannot take from a warehouse more than lies in it, though the branch has enough
        $sale = ['date' => today_iso(), 'party_id' => $customer->id, 'currency_id' => $afn->id, 'warehouse_id' => $store->id, 'lines' => [['item_id' => $rice->id, 'qty' => '60', 'price' => '3000']]];
        $this->post('/trade/sales', $sale)->assertSessionHas('danger');
        // 40 bags are moved to the store room: they keep their cost of 2,000, and the store room's average becomes (50 x 2,600 + 40 x 2,000) / 90
        $this->get('/stock-moves')->assertOk()->assertSee('Move goods');
        $this->post('/stock-moves', ['date' => today_iso(), 'item_id' => $rice->id, 'from_warehouse_id' => $first->id, 'to_warehouse_id' => $store->id, 'qty' => '40'])->assertSessionHasNoErrors()->assertSessionMissing('danger');
        $this->post('/stock-moves', ['date' => today_iso(), 'item_id' => $rice->id, 'from_warehouse_id' => $first->id, 'to_warehouse_id' => $store->id, 'qty' => '400'])->assertSessionHas('danger');
        $this->assertSame(['60.000', '90.000', '2333.3333', '150.000'], [Num::round($held($first)->stock, 3), Num::round($held($store)->stock, 3), Num::round($held($store)->avg_cost, 4), Num::round(Item::find($rice->id)->stock, 3)]);
        $this->get('/stock-moves')->assertOk()->assertSee('SM-000001');

        // now the sale goes through, at the cost of the store room
        $this->post('/trade/sales', $sale)->assertSessionMissing('danger');
        $this->assertSame(['30.000', '140000.00'], [Num::round($held($store)->stock, 3), Num::money(\App\Models\Invoice::where('kind', 'sale')->sole()->cost_total)]);
        $this->get('/reports/stock?warehouse='.$store->id)->assertOk()->assertSee('30');
        // a warehouse with goods or papers is not deleted, and the main one never
        $this->delete('/warehouses/'.$store->id);
        $this->delete('/warehouses/'.$first->id);
        $this->assertSame(2, \App\Models\Warehouse::count());
    }
}

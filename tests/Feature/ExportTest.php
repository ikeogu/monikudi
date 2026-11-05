<?php

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Every sheet of a downloaded workbook as rows of cell values, keyed by sheet name.
 */
function workbook(TestResponse $response): array
{
    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());

    $sheets = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $sheets[$sheet->getName()][] = $row->toArray();
        }
    }
    $reader->close();

    return $sheets;
}

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 30)->setTime(12, 0));

    $this->admin = User::factory()->create(['name' => 'Grace Admin']);
    $this->collector = User::factory()->collector()->create(['name' => 'Emeka']);
    $this->ada = Customer::factory()->create(['first_name' => 'Ada', 'last_name' => 'Obi', 'reg_no' => 'AJ0000007', 'phone' => '08012345678']);
    $bayo = Customer::factory()->create();
    $ledger = app(Ledger::class);

    $post = fn ($customer, $by, $type, $amount, $date) => $ledger->record($customer->plans()->first(), [
        'type' => $type, 'amount' => $amount, 'date' => $date, 'payment_method' => 'cash', 'collected_by' => $by->id,
    ], $by);

    $post($this->ada, $this->collector, Transaction::TYPE_DEPOSIT, 4000, '2026-10-02');
    $post($this->ada, $this->collector, Transaction::TYPE_DEPOSIT, 1000, '2026-10-29');
    $post($this->ada, $this->admin, Transaction::TYPE_WITHDRAWAL, 1500, '2026-10-30');
    $post($bayo, $this->admin, Transaction::TYPE_DEPOSIT, 500, '2026-10-30');
});

test('daily collections export a totals sheet and an entries sheet', function () {
    $response = $this->actingAs($this->admin)->get(route('collections.export', ['month' => '2026-10']));

    $response->assertOk()->assertDownload('collections-2026-10.xlsx');
    $sheets = workbook($response);

    expect(array_keys($sheets))->toBe(['Daily totals', 'Entries'])
        ->and($sheets['Daily totals'][0])->toBe(['Date', 'Day', 'Entries', 'Deposits', 'Withdrawals', 'Commission', 'Net', 'Running total'])
        ->and($sheets['Daily totals'])->toHaveCount(32) // heading, 1–30 Oct, total
        ->and($sheets['Daily totals'][30])->toEqual(['2026-10-30', 'Friday', 2, 500, 1500, 0, -1000, 4000])
        ->and(array_slice($sheets['Daily totals'][31], 0, 7))->toEqual(['Total', '', 4, 5500, 1500, 0, 4000])
        ->and($sheets['Entries'])->toHaveCount(5)
        ->and($sheets['Entries'][1])->toMatchArray([
            0 => '2026-10-02', 3 => 'AJ0000007', 4 => 'Ada Obi', 5 => '08012345678', 6 => 'Daily savings', 7 => 'Deposit', 8 => 4000,
        ]);
});

test('the collections export follows the filters and names the customer', function () {
    $response = $this->actingAs($this->admin)->get(route('collections.export', [
        'period' => 'range', 'from' => '2026-10-29', 'to' => '2026-10-30', 'customer' => $this->ada->id,
    ]));

    $response->assertDownload('collections-2026-10-29-to-2026-10-30-AJ0000007.xlsx');
    expect(workbook($response)['Entries'])->toHaveCount(3);
});

test('transactions export the filtered list, newest first', function () {
    $response = $this->actingAs($this->admin)->get(route('transactions.export', ['type' => Transaction::TYPE_DEPOSIT]));

    $response->assertDownload('deposits-2026-10-30.xlsx');
    $rows = workbook($response)['Deposits'];

    expect($rows)->toHaveCount(4)
        ->and(array_column($rows, 0))->toBe(['Date', '2026-10-30', '2026-10-29', '2026-10-02']);
});

test('collectors only export their own collections', function () {
    $response = $this->actingAs($this->collector)->get(route('transactions.export'));

    expect(workbook($response)['Transactions'])->toHaveCount(3)
        ->and(array_unique(array_column(array_slice(workbook($response)['Transactions'], 1), 10)))->toBe(['Emeka']);
});

test('reports export to Excel as well as CSV', function () {
    $response = $this->actingAs($this->admin)->get(route('reports.export', ['period' => 'monthly', 'date' => '2026-10-10', 'format' => 'xlsx']));

    $response->assertDownload('monthly-report-2026-10-01-to-2026-10-31.xlsx');
    expect(workbook($response)['Entries'])->toHaveCount(5)
        ->and(workbook($response)['Entries'][0])->toBe(Transaction::EXPORT_HEADINGS);
});

test('a customer statement exports with its opening and running balances', function () {
    $response = $this->actingAs($this->collector)->get(route('customers.export', ['customer' => $this->ada->id, 'from' => '2026-10-29']));

    $response->assertDownload('statement-AJ0000007-2026-10-29-to-today.xlsx');
    $rows = workbook($response)['AJ0000007'];

    expect($rows)->toHaveCount(4)
        ->and($rows[1])->toEqual(['2026-10-29', '', '', '', 'Opening balance', '', '', '', '', '', '', 4000])
        ->and([$rows[2][3], $rows[2][4], $rows[2][8], $rows[2][11]])->toEqual(['Daily savings', 'Deposit', 1000, 5000])
        ->and([$rows[3][4], $rows[3][9], $rows[3][11]])->toEqual(['Withdrawal', 1500, 3500]);
});

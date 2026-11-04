<?php

declare(strict_types=1);

/*
 * composer db:seed
 *
 * Recreates the three tables (inventory, payroll, employee_shift) and inserts sample data.
 * Works with SQLite (default) and MySQL (DB_DRIVER=mysql).
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\PayrollController;
use App\Core\App;
use App\Core\Database;
use App\Models\EmployeeShift;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\User;
use App\Support\Migrator;
use Illuminate\Database\Schema\Blueprint;

App::boot(dirname(__DIR__));

$schema = Database::schema();

$schema->disableForeignKeyConstraints();
$schema->dropIfExists('users');
$schema->dropIfExists('employee_shift');
$schema->dropIfExists('payroll');
$schema->dropIfExists('inventory');
$schema->enableForeignKeyConstraints();

/* ---------- Inventory (C#: Inventory) ---------- */
$schema->create('inventory', static function (Blueprint $t): void {
    $t->increments('product_id');
    $t->string('product_name', 100);
    $t->string('category', 50);
    $t->decimal('price', 10, 2);
    $t->integer('quantity')->default(0);
    $t->string('supplier', 100)->nullable();
    $t->date('date_added');
    $t->string('image_path', 255)->nullable();
    $t->timestamps();
    $t->index('category');
});

/* ---------- Payroll (C#: Payroll) ---------- */
$schema->create('payroll', static function (Blueprint $t): void {
    $t->increments('id');
    $t->string('employee_id', 50)->unique();
    $t->string('name', 100);
    $t->decimal('daily_rate', 10, 2)->default(550);
    $t->integer('days_worked')->default(0);
    $t->integer('ot_hours')->default(0);
    $t->integer('late_hours')->default(0);
    $t->decimal('deduction_per_hour', 10, 2)->default(0);
    $t->integer('holiday_present')->default(0);
    $t->string('holiday_type', 50)->nullable();
    $t->decimal('net_pay', 10, 2)->default(0);
    $t->decimal('gross_pay', 10, 2)->default(0);
    $t->decimal('total_deductions', 10, 2)->default(0);
    $t->decimal('sss', 10, 2)->default(0);
    $t->decimal('philhealth', 10, 2)->default(0);
    $t->decimal('pagibig', 10, 2)->default(0);
    $t->decimal('others', 10, 2)->default(0);
    $t->timestamps();
});

/* ---------- EmployeeShift (C#: EmployeeShift) ---------- */
$schema->create('employee_shift', static function (Blueprint $t): void {
    $t->increments('id');
    $t->string('payroll_employee_id', 50)->nullable();
    $t->string('names', 100);
    $t->string('schedule', 100)->nullable();
    $t->string('breaks', 100)->nullable();
    $t->string('duties', 200)->nullable();
    $t->string('day_off', 20)->nullable();
    $t->timestamps();

    $t->foreign('payroll_employee_id')
        ->references('employee_id')->on('payroll')
        ->nullOnDelete()->cascadeOnUpdate();
});

/* ---------- Sample data ---------- */
$img = static fn (string $f): string => 'uploads/products/' . $f;
$d = static fn (int $daysAgo): string => date('Y-m-d', strtotime("-{$daysAgo} days"));

$products = [
    ['BFC Hot Coffee',                 'BEVERAGES',   120.00, 40, 'But First Coffee Commissary', $d(20), $img('bfc-hot-coffee.jpg')],
    ['BFC Iced Coffee',                'BEVERAGES',   130.00, 55, 'But First Coffee Commissary', $d(20), $img('bfc-iced-coffee.jpg')],
    ['BFC Iced Choco',                 'BEVERAGES',   135.00, 30, 'But First Coffee Commissary', $d(18), $img('bfc-iced-choco.jpg')],
    ['BFC Iced Matcha',                'BEVERAGES',   150.00, 28, 'But First Coffee Commissary', $d(18), $img('bfc-iced-matcha.jpg')],
    ['BFC Iced Matcha Strawberry',     'BEVERAGES',   165.00, 22, 'But First Coffee Commissary', $d(15), $img('bfc-iced-matcha-strawberry.jpg')],
    ['BFC Iced Milky',                 'BEVERAGES',   125.00, 35, 'But First Coffee Commissary', $d(15), $img('bfc-iced-milky.jpg')],
    ['BFC Iced Ube',                   'BEVERAGES',   145.00, 18, 'But First Coffee Commissary', $d(12), $img('bfc-iced-ube.jpg')],
    ['Cinnamon Danish Croissant',      'CROISSANT',    95.00, 14, 'Floridablanca Bakehouse',     $d(5),  $img('cinnamon-danish-croissant.jpg')],
    ['Cream Cheese Pimiento Croissant', 'CROISSANT',  105.00, 12, 'Floridablanca Bakehouse',     $d(5),  $img('creamcheese-pimiento-croissant.jpg')],
    ['Golden Crunch Croissant',        'CROISSANT',    90.00, 16, 'Floridablanca Bakehouse',     $d(4),  $img('golden-crunch-croissant.jpg')],
    ['Ham & Cheese Croissant',         'CROISSANT',   115.00, 20, 'Floridablanca Bakehouse',     $d(4),  $img('ham-cheese-croissant.jpg')],
    ['Matcha Croissant',               'CROISSANT',   110.00,  9, 'Floridablanca Bakehouse',     $d(3),  $img('matcha-croissant.jpg')],
    ['Pistachio Kataifi Croissant',    'CROISSANT',   165.00,  6, 'Floridablanca Bakehouse',     $d(3),  $img('pistachio-kataifi-croissant.jpg')],
    ['Crookie',                        'COOKIES BROWNIES', 95.00, 24, 'Floridablanca Bakehouse', $d(2),  $img('crookie.jpg')],
    ['Espresso Beans 1kg',             'COFFEE',      980.00, 12, 'Benguet Coffee Traders',      $d(30), $img('default-placeholder.jpg')],
    ['Fresh Milk 1L',                  'DAIRY',        98.00, 48, 'Pampanga Dairy Co.',          $d(6),  $img('default-placeholder.jpg')],
    ['Vanilla Syrup 750ml',            'SYRUPS',      420.00,  8, 'Monin PH',                    $d(25), $img('default-placeholder.jpg')],
    ['Paper Cups 16oz (50s)',          'SUPPLIES',    260.00, 65, 'PackRight Supplies',          $d(28), $img('default-placeholder.jpg')],
];
foreach ($products as [$name, $cat, $price, $qty, $supplier, $date, $image]) {
    Product::create([
        'product_name' => $name, 'category' => $cat, 'price' => $price, 'quantity' => $qty,
        'supplier' => $supplier, 'date_added' => $date, 'image_path' => $image,
    ]);
}

$calculator = PayrollController::calculator();
$employees = [
    ['F-001', 'Juan Dela Cruz',    13, 4, 1, 50, 1, 'Regular', 450, 250, 200, 0],
    ['F-002', 'Maria Santos',      12, 2, 0, 50, 0, 'Regular', 450, 250, 200, 0],
    ['F-003', 'Paolo Reyes',       13, 6, 2, 50, 1, 'Special', 450, 250, 200, 100],
    ['F-004', 'Angela Garcia',     11, 0, 3, 50, 0, 'Regular', 450, 250, 200, 0],
    ['F-005', 'Mark Villanueva',   13, 3, 0, 50, 1, 'Regular', 450, 250, 200, 50],
    ['F-006', 'Kristine Mendoza',  12, 1, 1, 50, 0, 'Special', 450, 250, 200, 0],
];
foreach ($employees as [$id, $name, $days, $ot, $late, $ded, $hol, $type, $sss, $ph, $pi, $oth]) {
    $row = [
        'employee_id' => $id, 'name' => $name, 'days_worked' => $days, 'ot_hours' => $ot,
        'late_hours' => $late, 'deduction_per_hour' => $ded, 'holiday_present' => $hol,
        'holiday_type' => $type, 'sss' => $sss, 'philhealth' => $ph, 'pagibig' => $pi, 'others' => $oth,
    ];
    Payroll::create($row + $calculator->calculate($row));
}

$shifts = [
    ['F-001', 'Juan Dela Cruz',   '6:00 AM - 2:00 PM',  '10:00 AM - 11:00 AM', 'Opening Barista / Espresso Bar', 'Monday'],
    ['F-002', 'Maria Santos',     '6:00 AM - 2:00 PM',  '11:00 AM - 12:00 PM', 'Cashier / Order Taking',          'Tuesday'],
    ['F-003', 'Paolo Reyes',      '10:00 AM - 6:00 PM', '2:00 PM - 3:00 PM',   'Barista / Cold Bar',              'Wednesday'],
    ['F-004', 'Angela Garcia',    '10:00 AM - 6:00 PM', '3:00 PM - 4:00 PM',   'Pastry Display / Inventory',      'Thursday'],
    ['F-005', 'Mark Villanueva',  '2:00 PM - 10:00 PM', '6:00 PM - 7:00 PM',   'Closing Barista / Cleaning',      'Friday'],
    ['F-006', 'Kristine Mendoza', '2:00 PM - 10:00 PM', '7:00 PM - 8:00 PM',   'Cashier / Closing Report',        'Saturday'],
];
foreach ($shifts as [$pid, $name, $sched, $break, $duties, $off]) {
    EmployeeShift::create([
        'payroll_employee_id' => $pid, 'names' => $name, 'schedule' => $sched,
        'breaks' => $break, 'duties' => $duties, 'day_off' => $off,
    ]);
}

// users table + login accounts (admin, and one per Employee ID)
Migrator::up();

printf(
    "Seeded %d products, %d payroll records, %d shifts and %d login accounts (%s).\n",
    Product::count(),
    Payroll::count(),
    EmployeeShift::count(),
    User::count(),
    App::config('database.driver')
);

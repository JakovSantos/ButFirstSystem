<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Logger;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Support\EmployeeManager;
use App\Support\MainWindow;

/**
 * EMPLOYEES module (admin only): add, rename and remove employees,
 * and reset an employee's password when they forget it.
 */
final class EmployeeController
{
    public function index(): void
    {
        View::display('main', MainWindow::data('employees', $_GET));
    }

    /** POST /employees */
    public function store(): void
    {
        $rate = trim((string) ($_POST['daily_rate'] ?? ''));
        if ($rate !== '' && ! is_numeric($rate)) {
            $this->done(['ok' => false, 'message' => 'Daily rate must be a number.']);
        }

        $result = EmployeeManager::add(
            (string) ($_POST['employee_id'] ?? ''),
            (string) ($_POST['name'] ?? ''),
            $rate === '' ? null : (float) $rate,
        );
        $this->done($result, 'Employee Added');
    }

    /** POST /employees/{id}/name */
    public function rename(string $employeeId): void
    {
        $this->done(EmployeeManager::rename($employeeId, (string) ($_POST['name'] ?? '')), 'Name Updated');
    }

    /** POST /employees/{id}/delete */
    public function destroy(string $employeeId): void
    {
        $this->done(EmployeeManager::remove($employeeId), 'Employee Removed');
    }

    /** POST /employees/{id}/reset-password */
    public function resetPassword(string $employeeId): void
    {
        $this->done(EmployeeManager::resetPassword($employeeId), 'Password Reset');
    }

    /**
     * @param array{ok:bool,message:string} $result
     */
    private function done(array $result, string $caption = 'Employees'): never
    {
        if ($result['ok']) {
            Logger::info('Employees: ' . strtok($result['message'], "\n"));
            Session::flash($result['message'], $caption, 'information');
        } else {
            Session::flashInput(['employee' => [
                'employee_id' => (string) ($_POST['employee_id'] ?? ''),
                'name'        => (string) ($_POST['name'] ?? ''),
                'daily_rate'  => (string) ($_POST['daily_rate'] ?? ''),
            ]]);
            Session::flash($result['message'], 'Please Check', 'warning');
        }

        Response::redirect('/employees');
    }
}

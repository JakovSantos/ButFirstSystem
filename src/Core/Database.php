<?php

declare(strict_types=1);

namespace App\Core;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Eloquent (illuminate/database) used standalone through the Capsule manager.
 */
final class Database
{
    private static ?Capsule $capsule = null;

    /**
     * @param array<string, mixed> $config
     */
    public static function boot(array $config): void
    {
        if (self::$capsule !== null) {
            return;
        }

        if (($config['driver'] ?? '') === 'sqlite') {
            $file = (string) $config['database'];
            if (! is_file($file)) {
                if (! is_dir(dirname($file))) {
                    mkdir(dirname($file), 0775, true);
                }
                touch($file);
            }
            $config = [
                'driver'   => 'sqlite',
                'database' => $file,
                'prefix'   => '',
                'foreign_key_constraints' => true,
            ];
        }

        $capsule = new Capsule();
        $capsule->addConnection($config);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        self::$capsule = $capsule;
    }

    public static function capsule(): Capsule
    {
        if (self::$capsule === null) {
            throw new \RuntimeException('Database has not been booted. Call App::boot() first.');
        }

        return self::$capsule;
    }

    public static function schema(): \Illuminate\Database\Schema\Builder
    {
        return self::capsule()->schema();
    }
}

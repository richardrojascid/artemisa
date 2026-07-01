<?php
declare(strict_types=1);

require_once __DIR__ . '/ArtemisaMenuSeed.php';
require_once __DIR__ . '/Settings.php';

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            if (!is_dir(DATA_PATH)) {
                mkdir(DATA_PATH, 0755, true);
            }

            self::$instance = new PDO('sqlite:' . DB_PATH);
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$instance->exec('PRAGMA foreign_keys = ON');
        }

        return self::$instance;
    }

    public static function initialize(): void
    {
        $db = self::getConnection();

        $db->exec("
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                active INTEGER NOT NULL DEFAULT 1
            );

            CREATE TABLE IF NOT EXISTS menu_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                price REAL NOT NULL,
                price_double REAL,
                active INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS ingredients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                menu_item_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                removable INTEGER NOT NULL DEFAULT 1,
                FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS extras (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                menu_item_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                price REAL NOT NULL DEFAULT 0,
                FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                table_number TEXT,
                waiter_name TEXT,
                subtotal REAL NOT NULL,
                total REAL NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                created_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                menu_item_id INTEGER,
                item_name TEXT NOT NULL,
                unit_price REAL NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 1,
                extras_total REAL NOT NULL DEFAULT 0,
                line_total REAL NOT NULL,
                removed_ingredients TEXT,
                added_extras TEXT,
                notes TEXT,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            );
        ");

        Settings::ensureTable($db);
        self::migrate($db);

        $count = (int) $db->query('SELECT COUNT(*) FROM categories')->fetchColumn();
        if ($count === 0) {
            ArtemisaMenuSeed::seed($db);
        }
    }

    public static function install(string $pin): void
    {
        self::initialize();
        $settings = new Settings(self::getConnection());
        $settings->set('cafe_name', APP_NAME);
        $settings->setPin($pin);
        $settings->set('report_email', REPORT_EMAIL_DEFAULT);
        $settings->set('tip_percent', (string) TIP_PERCENT_DEFAULT);
    }

    public static function reseedMenu(): void
    {
        ArtemisaMenuSeed::seed(self::getConnection());
    }

    private static function migrate(PDO $db): void
    {
        $columns = $db->query('PRAGMA table_info(menu_items)')->fetchAll();
        $columnNames = array_column($columns, 'name');
        if (!in_array('price_double', $columnNames, true)) {
            $db->exec('ALTER TABLE menu_items ADD COLUMN price_double REAL');
        }

        $orderColumns = $db->query('PRAGMA table_info(orders)')->fetchAll();
        $orderColumnNames = array_column($orderColumns, 'name');
        if (!in_array('tip_amount', $orderColumnNames, true)) {
            $db->exec('ALTER TABLE orders ADD COLUMN tip_amount REAL NOT NULL DEFAULT 0');
        }
        if (!in_array('include_tip', $orderColumnNames, true)) {
            $db->exec('ALTER TABLE orders ADD COLUMN include_tip INTEGER NOT NULL DEFAULT 1');
        }
        if (!in_array('client_name', $orderColumnNames, true)) {
            $db->exec('ALTER TABLE orders ADD COLUMN client_name TEXT');
        }
        if (!in_array('order_type', $orderColumnNames, true)) {
            $db->exec('ALTER TABLE orders ADD COLUMN order_type TEXT NOT NULL DEFAULT \'servir\'');
        }

        $db->exec("UPDATE categories SET name = 'Salados' WHERE name = 'Saladas'");
        $db->exec("UPDATE categories SET name = 'Helados' WHERE name = 'Heladas'");
    }
}

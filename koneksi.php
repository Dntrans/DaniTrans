<?php
function envValue(string $key, $default = null)
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value !== false && $value !== null && $value !== '') {
        return $value;
    }

    return $default;
}

class PostgresCompatResult
{
    private $result;
    private $rows = [];
    private $index = 0;

    public function __construct($result)
    {
        $this->result = $result;
        $this->rows = pg_fetch_all($result) ?: [];
        $this->index = 0;
    }

    public function fetch_assoc()
    {
        if ($this->index >= count($this->rows)) {
            return null;
        }

        $row = $this->rows[$this->index];
        $this->index++;

        return $row;
    }

    public function fetch_all($mode = null)
    {
        return $this->rows;
    }

    public function free()
    {
        if ($this->result) {
            pg_free_result($this->result);
        }
    }

    public function __get($name)
    {
        if ($name === 'num_rows') {
            return pg_num_rows($this->result);
        }

        return null;
    }
}

class PostgresCompatStatement
{
    private $conn;
    private $sql;
    private $params = [];
    private $result = null;

    public function __construct($conn, $sql)
    {
        $this->conn = $conn;
        $this->sql = $sql;
    }

    public function bind_param($types, ...$values)
    {
        $this->params = $values;
        return true;
    }

    public function execute()
    {
        $sql = preg_replace_callback('/\?/', function () {
            static $counter = 0;
            $counter++;
            return '$' . $counter;
        }, $this->sql);

        $this->result = pg_query_params($this->conn, $sql, $this->params);

        return $this->result !== false;
    }

    public function get_result()
    {
        if ($this->result === null) {
            return null;
        }

        return new PostgresCompatResult($this->result);
    }

    public function store_result()
    {
        return true;
    }

    public function close()
    {
        if ($this->result !== null) {
            pg_free_result($this->result);
            $this->result = null;
        }
    }

    public function __get($name)
    {
        if ($name === 'num_rows' && $this->result !== null) {
            return pg_num_rows($this->result);
        }

        return null;
    }
}

class PostgresCompatConnection
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function query($sql)
    {
        $sql = $this->normalizeSql($sql);
        $result = pg_query($this->conn, $sql);

        if ($result === false) {
            return false;
        }

        return new PostgresCompatResult($result);
    }

    public function prepare($sql)
    {
        $sql = $this->normalizeSql($sql);
        return new PostgresCompatStatement($this->conn, $sql);
    }

    public function set_charset($charset)
    {
        return true;
    }

    public function close()
    {
        pg_close($this->conn);
    }

    private function normalizeSql($sql)
    {
        $sql = str_replace('`', '', trim($sql));
        $sql = str_replace('"Aktif"', "'Aktif'", $sql);
        $sql = str_replace('"Pending"', "'Pending'", $sql);
        $sql = str_replace('"Dikonfirmasi"', "'Dikonfirmasi'", $sql);
        $sql = str_replace('"Selesai"', "'Selesai'", $sql);
        $sql = str_replace('"Dibatalkan"', "'Dibatalkan'", $sql);
        $sql = str_replace('"Travel"', "'Travel'", $sql);
        $sql = str_replace('"Pribadi"', "'Pribadi'", $sql);
        $sql = str_replace('"Antar-Jemput"', "'Antar-Jemput'", $sql);
        $sql = str_replace('"Wisata"', "'Wisata'", $sql);
        $sql = str_replace('"Jawa Barat"', "'Jawa Barat'", $sql);
        $sql = str_replace('"Jawa Tengah"', "'Jawa Tengah'", $sql);
        $sql = str_replace('"Jawa Timur"', "'Jawa Timur'", $sql);
        $sql = str_replace('"Semua Area"', "'Semua Area'", $sql);

        if (str_contains($sql, 'ON DUPLICATE KEY UPDATE')) {
            $sql = preg_replace('/INSERT INTO\s+settings\s*\((.*?)\)\s*VALUES\s*\((.*?)\)\s*ON DUPLICATE KEY UPDATE\s*(.*)/is', 'INSERT INTO settings ($1) VALUES ($2) ON CONFLICT (setting_key) DO UPDATE SET $3', $sql);
            $sql = str_replace('setting_value = VALUES(setting_value)', 'setting_value = EXCLUDED.setting_value', $sql);
        }

        return $sql;
    }
}

$driver = strtolower((string) envValue('DB_DRIVER', envValue('DATABASE_DRIVER', 'postgres')));
$host = envValue('DB_HOST', 'db.imgairqftslzikveuk.supabase.co');
$username = envValue('DB_USERNAME', envValue('DB_USER', 'postgres'));
$password = envValue('DB_PASSWORD', envValue('DB_PASS', 'DaniTrans123@'));
$database = envValue('DB_NAME', envValue('DB_DATABASE', 'postgres'));
$port = envValue('DB_PORT', $driver === 'postgres' || $driver === 'supabase' ? '5432' : '3306');
$databaseUrl = envValue('DATABASE_URL', envValue('SUPABASE_DB_URL', 'postgresql://postgres:DaniTrans123%40@db.imgairqftslzikveuk.supabase.co:5432/postgres'));

if ($databaseUrl !== '') {
    $driver = 'postgres';
}

if ($driver === 'postgres' || $driver === 'supabase' || $databaseUrl !== '') {
    if (!function_exists('pg_connect')) {
        die('<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; border: 1px solid #f1c0c0; border-radius: 12px; background: #fff5f5; color: #7a1f1f;">Driver PostgreSQL belum aktif di PHP. Aktifkan ekstensi pgsql atau pakai mode MySQL lokal.</div>');
    }

    $connectionString = $databaseUrl !== ''
        ? $databaseUrl
        : "host={$host} port={$port} dbname={$database} user={$username} password={$password}";

    $conn = pg_connect($connectionString);

    if (!$conn) {
        die('<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; border: 1px solid #f1c0c0; border-radius: 12px; background: #fff5f5; color: #7a1f1f;">Koneksi PostgreSQL/Supabase gagal. Periksa DATABASE_URL atau variabel DB_HOST/DB_NAME/DB_USER.</div>');
    }

    $conn = new PostgresCompatConnection($conn);
} else {
    $conn = mysqli_connect($host, $username, $password, $database, (int) $port);

    if (!$conn) {
        error_log('Database connection failed: ' . mysqli_connect_error());
        die(
            '<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; border: 1px solid #f1c0c0; border-radius: 12px; background: #fff5f5; color: #7a1f1f;">' .
            '<h2 style="margin-top:0;">Database tidak terhubung</h2>' .
            '<p>Pastikan MySQL di XAMPP sudah aktif dan database <strong>' . htmlspecialchars($database, ENT_QUOTES, 'UTF-8') . '</strong> sudah dibuat.</p>' .
            '<p>Langkah cepat:</p>' .
            '<ol><li>Start MySQL di XAMPP Control Panel</li><li>Import file <strong>database.sql</strong> ke phpMyAdmin</li><li>Reload halaman ini</li></ol>' .
            '<p>Detail koneksi: ' . htmlspecialchars(mysqli_connect_error(), ENT_QUOTES, 'UTF-8') . '</p>' .
            '</div>'
        );
    }

    mysqli_set_charset($conn, 'utf8mb4');
}

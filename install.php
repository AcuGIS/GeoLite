<?php

/**
 * GeoLite Installation Handler
 * Handles the installation process including database setup and configuration
 */

class GeoLiteInstaller {
    private $db_host;
    private $db_name;
    private $db_user;
    private $db_pass;
    private $db_port;
    private $admin_password;
    private $pdo;
    private $errors = [];
    private $success_messages = [];

    public function __construct($config) {
        $this->db_host = trim($config['db_host'] ?? '');
        $this->db_name = trim($config['db_name'] ?? '');
        $this->db_user = trim($config['db_user'] ?? '');
        $this->db_pass = trim($config['db_pass'] ?? '');
        $this->db_port = trim($config['db_port'] ?? '5432');
        $this->admin_password = trim($config['admin_password'] ?? '');
    }

    public function validate() {
        $this->errors = [];

        if (empty($this->db_host)) {
            $this->errors[] = 'Database host is required.';
        }

        if (empty($this->db_name)) {
            $this->errors[] = 'Database name is required.';
        }

        if (empty($this->db_user)) {
            $this->errors[] = 'Database username is required.';
        }

        if (empty($this->db_pass)) {
            $this->errors[] = 'Database password is required.';
        }

        if (empty($this->admin_password)) {
            $this->errors[] = 'Admin password is required.';
        }

        if (!is_numeric($this->db_port) || $this->db_port < 1 || $this->db_port > 65535) {
            $this->errors[] = 'Database port must be a valid port number (1-65535).';
        }

        if (strlen($this->admin_password) < 6) {
            $this->errors[] = 'Admin password must be at least 6 characters long.';
        }

        return empty($this->errors);
    }

    public function testDatabaseConnection() {
        try {
            $this->pdo = new PDO(
                "pgsql:host={$this->db_host};port={$this->db_port};dbname={$this->db_name}",
                $this->db_user,
                $this->db_pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            return true;
        } catch (PDOException $e) {
            $this->errors[] = 'Database connection failed: ' . $e->getMessage();
            return false;
        }
    }

    public function updateConfigFile() {
        $const_content = "<?php
const DB_HOST=\"{$this->db_host}\";
const DB_NAME=\"{$this->db_name}\";
const DB_USER=\"{$this->db_user}\";
const DB_PASS=\"{$this->db_pass}\";
const DB_PORT = {$this->db_port};
const DB_SCMA='public';

const SESS_USR_KEY = 'qgis_user';
const WWW_DIR = '".__DIR__."';
const DATA_DIR = '".dirname(__DIR__)."/data';
";

        // Ensure incl directory exists
        if (!is_dir('incl')) {
            if (!mkdir('incl', 0755, true)) {
                $this->errors[] = 'Failed to create incl directory. Check permissions.';
                return false;
            }
        }

        if (file_put_contents('incl/const.php', $const_content) === false) {
            $this->errors[] = 'Failed to write const.php file. Check file permissions.';
            return false;
        }

        $this->success_messages[] = 'Configuration file updated successfully.';
        return true;
    }

    public function runDatabaseSetup() {
        try {
            // Read SQL files
            $setup_sql = file_get_contents('installer/setup.sql');
            $init_sql = file_get_contents('installer/init.sql');

            if ($setup_sql === false) {
                $this->errors[] = 'Failed to read installer/setup.sql file.';
                return false;
            }

            if ($init_sql === false) {
                $this->errors[] = 'Failed to read installer/init.sql file.';
                return false;
            }

            // Replace placeholder password with actual hash
            $admin_password_hash = password_hash($this->admin_password, PASSWORD_DEFAULT);
            $init_sql = str_replace('ADMIN_APP_PASS', $admin_password_hash, $init_sql);

            // Execute setup SQL (creates tables)
            $this->pdo->exec($setup_sql);
            $this->success_messages[] = 'Database tables created successfully.';

            // Execute init SQL (inserts initial data)
            $this->pdo->exec($init_sql);
            $this->success_messages[] = 'Initial data inserted successfully.';

            return true;
        } catch (PDOException $e) {
            $this->errors[] = 'Database setup failed: ' . $e->getMessage();
            return false;
        }
    }

    public function createDataDirectory() {
        $data_dir = __DIR__ . '/../data';
        if (!is_dir($data_dir)) {
            if (!mkdir($data_dir, 0755, true)) {
                $this->errors[] = 'Failed to create data directory. Check permissions.';
                return false;
            }
            
            if (!mkdir($data_dir.'/uploads', 0755, true)) {
                $this->errors[] = 'Failed to create data/uploads directory. Check permissions.';
                return false;
            }
        }

        if (!is_dir('assets')) {
            if (!mkdir('assets', 0755, true)) {
                $this->errors[] = 'Failed to create assets directory. Check permissions.';
                return false;
            }
        }

        if (!is_dir('assets/brand')) {
            if (!mkdir('assets/brand', 0755, true)) {
                $this->errors[] = 'Failed to create assets/brand directory. Check permissions.';
                return false;
            }
        }

        $this->success_messages[] = 'Required directories created successfully.';
        return true;
    }

    public function install() {
        if (!$this->validate()) {
            return false;
        }

        if (!$this->testDatabaseConnection()) {
            return false;
        }

        if (!$this->updateConfigFile()) {
            return false;
        }

        if (!$this->createDataDirectory()) {
            return false;
        }

        if (!$this->runDatabaseSetup()) {
            return false;
        }

        $this->success_messages[] = 'Installation completed successfully!';
        return true;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function getSuccessMessages() {
        return $this->success_messages;
    }

    public function hasErrors() {
        return !empty($this->errors);
    }

    public function getFirstError() {
        return !empty($this->errors) ? $this->errors[0] : '';
    }

    public function getAllMessages() {
        return array_merge($this->success_messages, $this->errors);
    }
    
    public function cleanup(){
        # remove install files
        $files = scandir('installer');
        foreach($files as $f){
            unlink('installer/'.$f);
        }
        rmdir('installer');
        unlink('install.php');
    }
}

// Check if already installed
if (file_exists('incl/const.php')) {
    $const_content = file_get_contents('incl/const.php');
    if (strpos($const_content, '${APP_DB}') === false) {
        // Already installed, redirect to main page
        header('Location: index.php');
        exit;
    }
}

$error = '';
$success = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $installer = new GeoLiteInstaller($_POST);
    
    if ($installer->install()) {
        $installer->cleanup();
        $success = implode(' ', $installer->getSuccessMessages()) . ' You can now <a href="index.php">access the application</a>.';
    } else {
        $error = $installer->getFirstError();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeoLite Installation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .install-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #667eea;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .logo p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .btn:hover {
            background: #5a6fd8;
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #363;
            border: 1px solid #cfc;
        }
        
        .alert-success a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .alert-success a:hover {
            text-decoration: underline;
        }
        
        .requirements {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .requirements h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .requirements ul {
            list-style: none;
            padding-left: 0;
        }
        
        .requirements li {
            padding: 5px 0;
            color: #666;
        }
        
        .requirements li:before {
            content: "✓";
            color: #28a745;
            font-weight: bold;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="logo">
            <h1>GeoLite</h1>
            <p>Map Builder & Dashboard Installation</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="install_check.php" class="btn" style="background: #28a745; text-decoration: none; display: inline-block; width: auto; padding: 10px 20px;">Check Installation Status</a>
            </div>
        <?php else: ?>
            <div class="requirements">
                <h3>Requirements</h3>
                <ul>
                    <li>PostgreSQL database server</li>
                    <li>PHP 7.4+ with PDO PostgreSQL extension</li>
                    <li>Web server (Apache/Nginx)</li>
                    <li>Write permissions for incl/ directory</li>
                </ul>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="db_host">Database Host</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="db_name">Database Name</label>
                        <input type="text" id="db_name" name="db_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_port">Port</label>
                        <input type="number" id="db_port" name="db_port" value="5432" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Database Username</label>
                    <input type="text" id="db_user" name="db_user" required>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">Database Password</label>
                    <input type="password" id="db_pass" name="db_pass" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_password">Admin Password</label>
                    <input type="password" id="admin_password" name="admin_password" placeholder="Password for admin user" required>
                </div>
                
                <button type="submit" class="btn">Install GeoLite</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

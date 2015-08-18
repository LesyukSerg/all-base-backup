<?
    # При запуску цього файлу:
    #   1. Будуть видалені всі архіви котрі були створені більше ніж %delay_delete% днів назад(в директорії росташування файлу)
    #   2. Будуть вибрані всі бази mysql на сервері localhost. I будуть створені їх дампи в форматі SQL
    #   3. Всі створені дампи будуть запаковані в ZIP архів(zip архів буде росташовано в директорії росташування файлу)

    set_time_limit(0);
    date_default_timezone_set('Europe/Kiev');

    $time_start = microtime(1);
    $dump_dir = dirname(__FILE__); # Директорія, для розміщення файлів

    $delay_delete = 90 * 24 * 3600; # Через скільки днів будуть видалені архіви (90 днів)
    $folder = 'backup_' . date("Y-m-d_His"); # Ім'я теки з архівами

    # Параметри підключення до бази данних
    $connect = array('host' => 'localhost', 'user' => 'root', 'pass' => '');

    deleteOldArchive($dump_dir, $delay_delete); # Видаляєм всі старі архіви

    $db_names = get_databases($connect); # Отримаємо сптсок всіх баз данних

    start($dump_dir, $folder, $connect, $db_names);

    echo 'Робота скрипта: ' . round(microtime(1) - $time_start, 4) . ' c<br />';


    # --- ФУНКЦІЇ --------------------------------------------------------------------
    function deleteOldArchive($dump_dir, $delay_delete) # Видаляєм всі старі архіви
    {
        $ts = time();
        $files = glob($dump_dir . "/backup_*");

        foreach ($files as $file) {
            if ($ts - filemtime($file) > $delay_delete) {
                echo 'Видалено старий дамп: ' . $file . '<br />';

                if (is_dir($file)) {
                    rmdir($file);
                } else {
                    unlink($file);
                }
            }
        }
    }

    function get_databases($connect) # Отримаємо сптсок всіх баз данних
    {
        $db_names = array();

        $db = new mysqli($connect['host'], $connect['user'], $connect['pass']); # З'єднуємося з базою даних
        $db->query("SET NAMES 'utf-8'"); # Встановлюємо кодування
        $result_set = $db->query('SHOW DATABASES');

        echo 'Бази котрі будуть оброблені: ';
        while ($base = $result_set->fetch_row()) {
            $db_names[] = $base[0];
            echo $base[0] . ', ';
        }
        $db->close(); # Закриваємо з'єднання з базою даних
        echo '<br />';

        return $db_names;
    }

    function start($dump_dir, $folder, $connect, $db_names)
    {
        echo 'створення дампу: ' . $folder . '<br />';

        if (is_dir($dump_dir . "/" . $folder)) exit; # Якщо такий архів вже є то вихід

        mkdir($dump_dir . "/" . $folder);

        $db_files = array(); # Масив імен баз данных
        $not = array();

        foreach ($db_names as $name) {
            if (!in_array($name, $not)) {
                $db_files[] = $name . '.sql';
                db_dump($connect, $dump_dir, $name);
            }
        }

        echo '---------------------------------------------<br />';

        foreach ($db_files as $file) {
            if (archivation($dump_dir, $folder, $file)) {
                unlink($dump_dir . "/" . $file);
                echo "<b><span style='color:green'>ВИДАЛЕНИЙ</span></b> файл <b>{$file}</b><br />";
                flush();
            }
        }
    }

    function db_dump($connect, $dump_dir, $db_name)
    {
        passthru('mysqldump --host=' . $connect['host'] . ' --user=' . $connect['user'] . ' --password=' . $connect['pass'] . ' ' . $db_name . ' > ' . $dump_dir . '/' . $db_name . '.sql');
        echo "<b><span style='color:green'>СТВОРЕНО</span></b> дамп бази <b>{$db_name}</b><br />";

        flush();
    }

    function archivation($dump_dir, $folder, $file)
    {
        $zip = new ZipArchive();
        $zip_path = $dump_dir . "/" . $folder . '/' . $file . '.zip';
        $path = $dump_dir . "/" . $file;

        if ($zip->open($zip_path, ZipArchive::CREATE) === true) {
            # Добавляємо в ZIP-архів всі дампи баз данных

            if (!$zip->addFile($path, $file)) {
                echo 'ERROR при додаванні файлу ' . $file . '<br />';
            }

            $zip->close();
            echo "<b><span style='color:green'>СТВОРЕНО</span></b> архів <b>{$zip_path}</b><br />";

            return true;
        }

        return false;
    }

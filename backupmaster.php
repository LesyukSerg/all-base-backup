<?php
    # При запуску цього файлу:
    #   1. Будуть видалені всі архіви котрі були створені більше ніж %delay_delete% днів назад(в директорії росташування файлу)
    #   2. Будуть вибрані всі бази mysql на сервері localhost. I будуть створені їх дампи в форматі SQL
    #   3. Всі створені дампи будуть запаковані в ZIP архів(zip архів буде росташовано в директорії росташування файлу)
    
    set_time_limit(0);
    date_default_timezone_set('Europe/Kiev');
    
    $timestart = microtime(1);
    //$dump_dir = getcwd(); # Директорія, для розміщення файлів
    $dump_dir = dirname(__FILE__); # Директорія, для розміщення файлів
    
    $delay_delete = 90 * 24 * 3600; # Через скільки днів будуть видалені архіви (90 днів)
    $filezip = 'backup_'.date("Y-m-d_His").'.zip'; # Ім'я архіву
    
    # Параметри підключення до бази данних
    $connect = array('host' => 'localhost', 'user' => 'root', 'pass' => '');
    
    deleteOldArchive($dump_dir, $delay_delete); # Видаляєм всі старі архіви
    
    $db_names = get_databases($connect); # Отримаємо сптсок всіх баз данних
    
    start($dump_dir, $filezip, $connect, $db_names);
    
    echo 'Робота скрипта: '.round(microtime(1)-$timestart, 4).' c<br />';
    
    
# ---ФУНКЦІЇ--------------------------------------------------------------------
    function get_databases($cnct) # Отримаємо сптсок всіх баз данних
    { 
        $db_names = array();
        
        $db = new mysqli($cnct['host'], $cnct['user'], $cnct['pass']); # З'єднуємося з базою даних
        $db->query("SET NAMES 'utf-8'"); # Встановлюємо кодування
        $result_set = $db->query('SHOW DATABASES');
        
        echo 'Бази котрі будуть оброблені: ';
        while ($dbase = $result_set->fetch_row()) {
            $db_names[] = $dbase[0];
            echo $dbase[0].', ';
        }
        $db->close(); # Закриваємо з'єднання з базою даних
        echo '<br />';
        
        return $db_names;
    }
    
    function deleteOldArchive($dump_dir, $delay_delete) # Видаляєм всі старі архіви
    {
        $ts = time();
        $files = glob($dump_dir."/*.zip");
        foreach ($files as $file) {
            if ($ts - filemtime($file) > $delay_delete) {
                echo 'Видалено старий дамп: '.$file.'<br />';
                unlink($file);
            }
        }
    }
    
    function start($dump_dir, $filezip, $connect, $db_names)
    {
        echo 'створення дампу: '.$filezip.'<br />';
        
        if (file_exists($dump_dir."/".$filezip)) exit; # Якщо такий архів вже є то вихід
        
        $db_files = array(); # Масив імен баз данных
        foreach ($db_names as $name) {
            $db_files[] = $dump_dir.'/'.$name.'.sql';
            db_dump($connect, $dump_dir, $name);
        }
        
        echo '---------------------------------------------<br />';
        archivation($dump_dir, $filezip, $db_files);
        
        foreach ($db_files as $file) {
            unlink($file);
            echo '<b><font color="green">ВИДАЛЕНИЙ</font></b> архів <b>'.$file.'</b><br />';
        }
    }
    
    function db_dump($cnct, $dump_dir, $db_name)
    {
        passthru('mysqldump --host='.$cnct['host'].' --user='.$cnct['user'].' --password='.$cnct['pass'].' '.$db_name.' > '.$dump_dir.'/'.$db_name.'.sql');
        echo '<b><font color="green">СТВОРЕНО</font></b> дамп бази <b>'.$db_name.'</b><br />';
        
        flush();
    }
    
    function archivation($dump_dir, $filezip, $db_files)
    {
        $zip = new ZipArchive();
        if ($zip->open($dump_dir."/".$filezip, ZipArchive::CREATE) === true) {
            # Добавляємо в ZIP-архів всі дампи баз данных
            $n = strlen($dump_dir);
            $n++;
            foreach ($db_files as $file) {
                $local = substr($file, $n);
                if (!$zip->addFile($file, $local)) {
                    echo 'ERROR при додаванні файлу '.$file.'<br />';
                }
            }
            $zip->close();
            echo '<b><font color="green">СТВОРЕНО</font></b> архив <b>'.$filezip.'</b><br />';
        }
    }

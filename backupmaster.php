<?php
    set_time_limit(0); // Убираем ограничение на максимальное время работы скрипта
    date_default_timezone_set('Europe/Kiev');
    ini_set('memory_limit', '64M');
    
    $dump_dir = getcwd(); // Директория, куда будут помещаться архивы
    $delay_delete = 90 * 24 * 3600; // Время в секундах, через которое архивы будут удаляться
    $filezip = 'backup_'.date("Y-m-d_His").'.zip'; // Имя архива
    $timestart = microtime(1);
    $db_names = array();
    
    /* Параметры подключения к базе данных */
    $connect = array(
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
    );
    
    deleteOldArchive($dump_dir, $delay_delete); // Удаляем все старые архивы
    
    $db_names = get_databases($connect); // Получаем все базы данных
    //$db_names[] = 'sgifts_base';
    
    start($dump_dir, $filezip, $connect, $db_names);
    
    echo 'выполнение скрипта: '.round(microtime(1)-$timestart, 4).' c<br />';
    
    
    
# ---ФУНКЦІЇ--------------------------------------------------------------------
    function get_databases($cnct)
    { // Получаем все базы
        $db_names = array();
        
        $db = new mysqli($cnct['host'], $cnct['user'], $cnct['pass']); // Соединяемся с базой данных
        $db->query("SET NAMES 'utf-8'"); // Устанавливаем кодировку соединения
        $result_set = $db->query('SHOW DATABASES');
        
        echo 'базы которые будут обработаны: ';
        while ($dbase = $result_set->fetch_row()) {
            $db_names[] = $dbase[0];
            echo $dbase[0].', ';
        }
        $db->close(); // Закрываем соединение с базой данных и переходим к следующей
        echo '<br />';
        
        return $db_names;
    }
    
    function deleteOldArchive($dump_dir, $delay_delete) // Удаляем все старые архивы
    {
        $ts = time();
        $files = glob($dump_dir."/*.zip");
        foreach ($files as $file) {
            if ($ts - filemtime($file) > $delay_delete) {
                unlink($file);
            }
        }
    }
    
    function start($dump_dir, $filezip, $connect, $db_names)
    {
        echo 'создание дампа: '.$filezip.'<br />';
        
        if (file_exists($dump_dir."/".$filezip)) exit; // Если архив с таким именем уже есть, то заканчиваем скрипт
        
        $db_files = array(); // Массив, куда будут помещаться файлы с дампом баз данных
        foreach ($db_names as $name) {
            $db_files[] = $dump_dir.'/'.$name.'.gz'; // Помещаем файл в массив
            db_dump($connect, $dump_dir, $name);
        }
        
        echo '---------------------------------------------<br />';
        archivation($dump_dir, $filezip, $db_files);
        
        foreach ($db_files as $file) {
            unlink($file);
            echo '<b><font color="green">УДАЛЕН</font></b> архив <b>'.$file.'</b><br />';
        }
    }
    
    function db_dump($cnct, $dump_dir, $db_name)
    {
        passthru('mysqldump --host='.$cnct['host'].' --user='.$cnct['user'].' --password='.$cnct['pass'].' '.$db_name.' > '.$db_name.'.gz');
        echo '<b><font color="green">СОЗДАН</font></b> дамп базы <b>'.$db_name.'</b><br />';
        
        flush();
    }
    
    function archivation($dump_dir, $filezip, $db_files)
    {
        $zip = new ZipArchive(); // Создаём объект класса ZipArchive
        if ($zip->open($dump_dir."/".$filezip, ZipArchive::CREATE) === true) {
            /* Добавляем в ZIP-архив все дампы баз данных */
            $n = strlen($dump_dir);
            $n++;
            foreach ($db_files as $file) {
                $local = substr($file, $n);
                if (!$zip->addFile($file, $local)) {
                    echo 'ОШИБКА при добавлении файла '.$file.'<br />';
                }
            }
            $zip->close();
            echo '<b><font color="green">СОЗДАН</font></b> архив <b>'.$filezip.'</b><br />';
        }
    }

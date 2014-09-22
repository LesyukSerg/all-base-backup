<?php
    set_time_limit(0); // Убираем ограничение на максимальное время работы скрипта
    /* Массив с именами баз данных (имя базы данных можно посмотреть в phpMyAdmin) */
    $db_names = array();
    $db_names[] = 'obuv_shoesua';
    $db_names[] = 'exposzzh_opt';
    $db_names[] = 'sgifts_base';
    //$db_names[] = "db2";
 
    /* Массив с именами директорий, в которых лежат все файлы сайта */
    /* ВАЖНО: Все пути должны быть физическими и идти от корня сервера. Точный физический путь можно посмотреть через phpinfo() */
    $source_dirs = array();
    $source_dirs[] = getcwd().'/';
    //$source_dirs[] = "/home/mysite2.ru";
 
    $offset_dirs = strlen('/home/'); // Служебная переменная, служащая для устранения лишних папок в архиве
 
    /* Параметры подключения к базе данных */
    $host = 'localhost';
    $user = 'root';
    $password = '';
 
    $dump_dir = getcwd().'/../backup'; // Директория, куда будут помещаться архивы
    $delay_delete = 35 * 24 * 3600; // Время в секундах, через которое архивы будут удаляться
    $filezip = "backup_".date("Y-m-d_His").".zip"; // Имя архива
 
    deleteOldArchive(); // Удаляем все старые архивы
 
    if (file_exists($dump_dir."/".$filezip)) exit; // Если архив с таким именем уже есть, то заканчиваем скрипт
 
    $db_files = array(); // Массив, куда будут помещаться файлы с дампом баз данных
 
    for ($i = 0; $i < count($db_names); $i++) {
        $filename = $db_names[$i].".sql"; // Имя файла с дампом базы данных
        $db_files[] = $dump_dir."/".$filename; // Помещаем файл в массив
        $fp = fopen($dump_dir."/".$filename, "a"); // Открываем файл
        $db = new mysqli($host, $user, $password, $db_names[$i]); // Соединяемся с базой данных
        $db->query("SET NAMES 'utf-8'"); // Устанавливаем кодировку соединения
        $result_set = $db->query("SHOW TABLES"); // Запрашиваем все таблицы из базы
        while (($table = $result_set->fetch_assoc()) != false) {
            /* Перебор всех таблиц в базе данных */
            $table = array_values($table);
            if ($fp) {
                $result_set_table = $db->query("SHOW CREATE TABLE `".$table[0]."`"); // Получаем запрос на создание таблицы
                $query = $result_set_table->fetch_assoc();
                $query = array_values($query);
                fwrite($fp, "\n".$query[1].";\n"); // Добавляем результат в файл
                $rows = "SELECT * FROM `".$table[0]."`";
                $result_set_rows = $db->query($rows); // Получаем список всех записей в таблице
                while (($row = $result_set_rows->fetch_assoc()) != false) {
                    $query = "";
                    /* Путём перебора всех записей добавляем запросы на их создание в файл */
                    foreach ($row as $field) {
                        if (is_null($field)) $field = "NULL";
                        else $field = "'".$db->real_escape_string($field)."'"; // Экранируем значения
                        if ($query == "") $query = $field;
                        else $query .= ", ".$field;
                    }
                    $query = "INSERT INTO `".$table[0]."` VALUES (".$query.");";
                    fwrite($fp, $query);
                }
            }
        }
        fclose($fp); // Закрываем файл
        $db->close(); // Закрываем соединение с базой данных и переходим к следующей
        echo 'ДАМП БАЗЫ <b>'.$filename.'</b> СОЗДАН <br />';
        flush();
    }
 
    $zip = new ZipArchive(); // Создаём объект класса ZipArchive
    $allfiles = array(); // Массив со списком всех файлов, которые будут помещены в архив
    if ($zip->open($dump_dir."/".$filezip, ZipArchive::CREATE) === true) {
        /* for ($i = 0; $i < count($source_dirs); $i++) {
            // Рекурсивный перебор всех директорий
            if (is_dir($source_dirs[$i])) recoursiveDir($source_dirs[$i]);
            else $allfiles[] = $source_dirs[$i]; // Добавляем файл в итоговый массив
            foreach ($allfiles as $val){
                // Добавляем в ZIP-архив все полученные файлы
                $local = substr($val, $offset_dirs);
                $zip->addFile($val, $local);
            }
        } */
        /* Добавляем в ZIP-архив все дампы баз данных */
        for ($i = 0; $i < count($db_files); $i++) {
            $local = substr($db_files[$i], strlen($dump_dir) + 1);
            $zip->addFile($db_files[$i], $local);
        }
        $zip->close();
    }
 
    for ($i = 0; $i < count($db_files); $i++) unlink($db_files[$i]); // Очищаем массив db_files
 
    /* Функция для рекурсивного перебора и сохранения всех файлов и папок в массив, который затем возвращается */
    function recoursiveDir($dir){
        global $allfiles;
        if ($files = glob($dir."/{,.}*", GLOB_BRACE)) {
            foreach($files as $file){
                $b_name = basename($file);
                if (($b_name == ".") || ($b_name == "..")) continue;
                if (is_dir($file)) recoursiveDir($file);
                else $allfiles[] = $file;
            }
        }
    }
 
    /* Функция для удаления всех старых архивов */
    function deleteOldArchive() {
        global $dump_dir;
        global $delay_delete;
        $ts = time();
        $files = glob($dump_dir."/*.zip");
        foreach ($files as $file)
            if ($ts - filemtime($file) > $delay_delete) unlink($file);
    }
?>
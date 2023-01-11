<?php

namespace App;

function find($id)
{
    $file = __DIR__ . '/../users/users.txt';
    $lines = explode(PHP_EOL, trim(file_get_contents($file)));
    foreach ($lines as $line) {
        $scorer = json_decode($line, true);
        if (($scorer['id']) === $id) {
            return $scorer;
            break;
        }
    }
    return false;
}

function replace($id, $subject)
{
    $file = __DIR__ . '/../users/users.txt';
    $scorers = explode(PHP_EOL, trim(file_get_contents($file)));
    $updatedScorers = array();
    foreach ($scorers as $line) {
        $scorer = json_decode($line, true);
        if (($scorer['id']) === $id) {
            $scorer['nickname'] = $subject['nickname'];
            $scorer['email'] = $subject['email'];
        }
        $updatedScorers[] = json_encode($scorer);
    }
    file_put_contents($file, implode(PHP_EOL, $updatedScorers) . PHP_EOL);
}

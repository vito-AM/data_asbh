<?php
session_start();
require_once 'db.php';
$pdo = getBD();

/* 1. Vérifs basiques */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID invalide');
}
$id = (int) $_GET['id'];


/* 3. Suppression transactionnelle */
$pdo->beginTransaction();

$tables = [
  'courir',
  'export_stat_match',
  'points',
  'fin_actions_collectives',
  'score',
  'localisation',
  'temps_effectif',
  'possession_mt_1',
  'possession_mt_2',
  'idp'
];

foreach ($tables as $t) {
    $pdo->prepare("DELETE FROM $t WHERE id_match = ?")->execute([$id]);
}
$pdo->prepare("DELETE FROM `match` WHERE id_match = ?")->execute([$id]);

$pdo->commit();

/* 4. Redirection */
header('Location: matchs.php?deleted=1');
exit;

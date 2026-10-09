<?php
namespace lib;

final class DbTransaction
{
    public static function run($db,callable $callback)
    {
        $pdo=$db->db;
        if ($pdo->inTransaction()) return $callback();
        $mode=$pdo->getAttribute(\PDO::ATTR_ERRMODE);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
        $pdo->beginTransaction();
        try { $result=$callback(); $pdo->commit(); return $result; }
        catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        finally { $pdo->setAttribute(\PDO::ATTR_ERRMODE,$mode); }
    }
}

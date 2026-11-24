<?php
/**
 * Usuarios y roles.
 *  - admin:    dueño del taller. Hace todo lo del empleado y además ve el dinero y da de alta empleados.
 *  - empleado: personal del taller. Órdenes, presupuestos, clientes e historial de empleados.
 *  - cliente:  ve sus autos, aprueba o rechaza presupuestos y puede pedir una orden nueva.
 */
class Usuarios
{
    public const ROLES_PERSONAL = ['admin', 'empleado'];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** ¿Es personal del taller (admin o empleado)? */
    public static function esPersonal(string $rol): bool
    {
        return in_array($rol, self::ROLES_PERSONAL, true);
    }

    public static function nombreRol(string $rol): string
    {
        return ['admin' => 'Admin', 'empleado' => 'Empleado', 'cliente' => 'Cliente'][$rol] ?? $rol;
    }

    public function buscar(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM usuarios WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /**
     * Corrobora quién es el empleado: el email y la contraseña tienen que ser de
     * un admin o empleado activo. Devuelve el usuario o null.
     * Se usa para cambiar un presupuesto que ya se le mandó al cliente.
     */
    public function verificarPersonal(string $email, string $password): ?array
    {
        $st = $this->db->prepare('SELECT * FROM usuarios WHERE email = ?');
        $st->execute([trim($email)]);
        $u = $st->fetch();
        if (!$u || !self::esPersonal($u['rol']) || !(int)$u['activo']) {
            return null;
        }
        return password_verify($password, $u['password_hash']) ? $u : null;
    }

    /** Alta de un empleado. Devuelve la lista de errores (vacía si salió bien). */
    public function crearEmpleado(string $nombre, string $email, ?string $telefono, string $password, string $rol = 'empleado'): array
    {
        $errores = Validador::errores([
            Validador::obligatorio($nombre, 'Nombre', 100),
            Validador::email($email),
            mb_strlen($password) >= 6 ? null : 'La contraseña tiene que tener al menos 6 caracteres.',
            self::esPersonal($rol) ? null : 'Rol inválido.',
        ]);
        if (!$errores) {
            $st = $this->db->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ?');
            $st->execute([trim($email)]);
            if ($st->fetchColumn() > 0) {
                $errores[] = 'Ya existe un usuario con ese email.';
            }
        }
        if (!$errores) {
            $this->db->prepare(
                'INSERT INTO usuarios (nombre, email, password_hash, rol, telefono, activo, creado_en) VALUES (?, ?, ?, ?, ?, 1, ?)'
            )->execute([trim($nombre), trim($email), password_hash($password, PASSWORD_DEFAULT), $rol, $telefono ?: null, date('Y-m-d H:i:s')]);
        }
        return $errores;
    }

    public function cambiarActivo(int $id, bool $activo): void
    {
        $this->db->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')->execute([$activo ? 1 : 0, $id]);
    }
}

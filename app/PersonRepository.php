<?php
declare(strict_types=1);

final class PersonRepository
{
    public function __construct(private PDO $pdo) {}

    public function create(array $input): int
    {
        // Defensa adicional: ninguna llamada al repositorio puede omitir validación PHP.
        $validation = Validation::person($input);
        if (!$validation['valid']) {
            throw new InvalidArgumentException('No se pueden guardar registros inválidos.');
        }
        $statement = $this->pdo->prepare('INSERT INTO personas (nombre, telefono, dpi, correo) VALUES (:nombre, :telefono, :dpi, :correo)');
        $statement->execute($validation['data']);
        return (int)$this->pdo->lastInsertId();
    }

    public function search(string $query, int $page = 1): array
    {
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';
        $where = "WHERE nombre LIKE :nombre ESCAPE '!' OR telefono LIKE :telefono ESCAPE '!' OR dpi LIKE :dpi ESCAPE '!' OR correo LIKE :correo ESCAPE '!'";
        $parameters = ['nombre' => $like, 'telefono' => $like, 'dpi' => $like, 'correo' => $like];
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM personas ' . $where);
        $count->execute($parameters);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / 20));
        $page = max(1, min($page, $pages));
        $statement = $this->pdo->prepare('SELECT id, nombre, telefono, dpi, correo, created_at FROM personas ' . $where . ' ORDER BY id DESC LIMIT :limit OFFSET :offset');
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', 20, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * 20, PDO::PARAM_INT);
        $statement->execute();
        return ['records' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM personas WHERE id = :id');
        $statement->execute(['id' => $id]);
        return $statement->rowCount() === 1;
    }
}

<?php
/**
 * Praktikum 5: Endpoint REST API Mahasiswa Berbasis MySQL & PDO
 * File: api/mahasiswa.php
 * Mendukung metode HTTP GET, POST, PUT, DELETE dengan respon format JSON.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
} catch (PDOException $e) {
    respond(500, [
        'status' => 'error',
        'message' => 'Gagal terhubung ke database: ' . $e->getMessage(),
        'data' => null,
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$id = null;
if (array_key_exists('id', $_GET)) {
    if (!is_string($_GET['id']) && !is_int($_GET['id'])) {
        respond(400, ['status' => 'error', 'message' => 'ID mahasiswa harus berupa bilangan bulat positif.', 'data' => null]);
        exit;
    }

    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        respond(400, ['status' => 'error', 'message' => 'ID mahasiswa harus berupa bilangan bulat positif.', 'data' => null]);
        exit;
    }
}

switch ($method) {
    case 'GET':
        handleGet($pdo, $id);
        break;

    case 'POST':
        handlePost($pdo);
        break;

    case 'PUT':
        handlePut($pdo, $id);
        break;

    case 'DELETE':
        handleDelete($pdo, $id);
        break;

    default:
        respond(405, [
            'status' => 'error',
            'message' => "Metode HTTP {$method} tidak diizinkan.",
            'data' => null,
        ]);
}

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleGet(PDO $pdo, ?int $id): void
{
    try {
        if ($id !== null) {
            $stmt = $pdo->prepare('SELECT id, npm, nama, program_studi, angkatan, email, created_at FROM mahasiswa WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $mahasiswa = $stmt->fetch();

            if (!$mahasiswa) {
                respond(404, ['status' => 'error', 'message' => "Mahasiswa dengan ID {$id} tidak ditemukan.", 'data' => null]);
                return;
            }

            respond(200, ['status' => 'success', 'data' => $mahasiswa]);
            return;
        }

        $searchInput = $_GET['search'] ?? '';
        if (!is_string($searchInput)) {
            respond(400, ['status' => 'error', 'message' => 'Parameter pencarian harus berupa teks.', 'data' => null]);
            return;
        }

        $search = trim($searchInput);
        if ($search !== '') {
            $stmt = $pdo->prepare('SELECT id, npm, nama, program_studi, angkatan, email, created_at FROM mahasiswa WHERE npm LIKE :kw1 OR nama LIKE :kw2 OR program_studi LIKE :kw3 ORDER BY id DESC');
            $keyword = "%{$search}%";
            $stmt->execute([':kw1' => $keyword, ':kw2' => $keyword, ':kw3' => $keyword]);
        } else {
            $stmt = $pdo->query('SELECT id, npm, nama, program_studi, angkatan, email, created_at FROM mahasiswa ORDER BY id DESC');
        }

        $result = $stmt->fetchAll();
        respond(200, ['status' => 'success', 'total' => count($result), 'data' => $result]);
    } catch (PDOException $e) {
        respond(500, ['status' => 'error', 'message' => 'Gagal mengambil data mahasiswa.', 'data' => null]);
    }
}

function handlePost(PDO $pdo): void
{
    $input = readMahasiswaInput(true);
    if ($input === null) {
        return;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO mahasiswa (npm, nama, program_studi, angkatan, email) VALUES (:npm, :nama, :prodi, :angkatan, :email)');
        $stmt->execute([
            ':npm' => $input['npm'],
            ':nama' => $input['nama'],
            ':prodi' => $input['program_studi'],
            ':angkatan' => $input['angkatan'],
            ':email' => $input['email'],
        ]);

        $input['id'] = (int)$pdo->lastInsertId();
        respond(201, ['status' => 'success', 'message' => 'Data mahasiswa berhasil ditambahkan.', 'data' => $input]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            respond(409, ['status' => 'error', 'message' => 'NPM sudah terdaftar.', 'data' => null]);
            return;
        }

        respond(500, ['status' => 'error', 'message' => 'Gagal menambahkan data mahasiswa.', 'data' => null]);
    }
}

function handlePut(PDO $pdo, ?int $id): void
{
    if ($id === null) {
        respond(400, ['status' => 'error', 'message' => 'ID mahasiswa wajib disertakan.', 'data' => null]);
        return;
    }

    $input = readMahasiswaInput(true);
    if ($input === null) {
        return;
    }

    try {
        $stmt = $pdo->prepare('UPDATE mahasiswa SET npm = :npm, nama = :nama, program_studi = :prodi, angkatan = :angkatan, email = :email WHERE id = :id');
        $stmt->execute([
            ':npm' => $input['npm'],
            ':nama' => $input['nama'],
            ':prodi' => $input['program_studi'],
            ':angkatan' => $input['angkatan'],
            ':email' => $input['email'],
            ':id' => $id,
        ]);

        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare('SELECT id FROM mahasiswa WHERE id = :id');
            $check->execute([':id' => $id]);
            if (!$check->fetch()) {
                respond(404, ['status' => 'error', 'message' => "Mahasiswa dengan ID {$id} tidak ditemukan.", 'data' => null]);
                return;
            }
        }

        $input['id'] = $id;
        respond(200, ['status' => 'success', 'message' => 'Data mahasiswa berhasil diperbarui.', 'data' => $input]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            respond(409, ['status' => 'error', 'message' => 'NPM sudah digunakan oleh mahasiswa lain.', 'data' => null]);
            return;
        }

        respond(500, ['status' => 'error', 'message' => 'Gagal memperbarui data mahasiswa.', 'data' => null]);
    }
}

function handleDelete(PDO $pdo, ?int $id): void
{
    if ($id === null) {
        respond(400, ['status' => 'error', 'message' => 'ID mahasiswa wajib disertakan.', 'data' => null]);
        return;
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM mahasiswa WHERE id = :id');
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            respond(404, ['status' => 'error', 'message' => "Mahasiswa dengan ID {$id} tidak ditemukan.", 'data' => null]);
            return;
        }

        respond(200, ['status' => 'success', 'message' => 'Data mahasiswa berhasil dihapus.', 'data' => null]);
    } catch (PDOException $e) {
        respond(500, ['status' => 'error', 'message' => 'Gagal menghapus data mahasiswa.', 'data' => null]);
    }
}

function readMahasiswaInput(bool $allowFormData): ?array
{
    $body = file_get_contents('php://input');
    $input = json_decode($body, true);

    if ($body === '' && $allowFormData) {
        $input = $_POST;
    } elseif (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
        respond(400, ['status' => 'error', 'message' => 'Body request harus berupa objek JSON yang valid.', 'data' => null]);
        return null;
    }

    $fields = ['npm', 'nama', 'program_studi', 'email'];
    foreach ($fields as $field) {
        if (!isset($input[$field]) || !is_string($input[$field]) || trim($input[$field]) === '') {
            respond(400, ['status' => 'error', 'message' => 'Seluruh bidang isian wajib diisi.', 'data' => null]);
            return null;
        }
        $input[$field] = trim($input[$field]);
    }

    $angkatanInput = $input['angkatan'] ?? null;
    if (!is_string($angkatanInput) && !is_int($angkatanInput)) {
        respond(400, ['status' => 'error', 'message' => 'Angkatan harus berupa bilangan bulat positif.', 'data' => null]);
        return null;
    }

    $angkatan = filter_var($angkatanInput, FILTER_VALIDATE_INT);
    if ($angkatan === false || $angkatan < 1) {
        respond(400, ['status' => 'error', 'message' => 'Angkatan harus berupa bilangan bulat positif.', 'data' => null]);
        return null;
    }

    if (filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false) {
        respond(400, ['status' => 'error', 'message' => 'Format email tidak valid.', 'data' => null]);
        return null;
    }

    return [
        'npm' => $input['npm'],
        'nama' => $input['nama'],
        'program_studi' => $input['program_studi'],
        'angkatan' => $angkatan,
        'email' => $input['email'],
    ];
}

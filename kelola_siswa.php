<?php
// kelola_siswa.php
include 'koneksi.php';

$pesan = '';
$pesan_type = '';

// Handle Delete
if (isset($_POST['delete_siswa'])) {
    $id = mysqli_real_escape_string($koneksi, $_POST['id']);
    
    // Delete related absensi records first (due to foreign key constraint)
    $sql_delete_absensi = "DELETE FROM absensi WHERE id_siswa = ?";
    $stmt_delete_absensi = mysqli_prepare($koneksi, $sql_delete_absensi);
    mysqli_stmt_bind_param($stmt_delete_absensi, "i", $id);
    mysqli_stmt_execute($stmt_delete_absensi);
    mysqli_stmt_close($stmt_delete_absensi);
    
    // Then delete the student
    $sql_delete = "DELETE FROM siswa WHERE id = ?";
    $stmt_delete = mysqli_prepare($koneksi, $sql_delete);
    mysqli_stmt_bind_param($stmt_delete, "i", $id);
    
    if (mysqli_stmt_execute($stmt_delete)) {
        $pesan = "Data siswa berhasil dihapus!";
        $pesan_type = "success";
    } else {
        $pesan = "Gagal menghapus data siswa: " . mysqli_error($koneksi);
        $pesan_type = "error";
    }
    mysqli_stmt_close($stmt_delete);
}

// Handle Update
if (isset($_POST['update_siswa'])) {
    $id = mysqli_real_escape_string($koneksi, $_POST['id']);
    $nama_lengkap = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $kelas = mysqli_real_escape_string($koneksi, $_POST['kelas']);
    $uid_rfid = mysqli_real_escape_string($koneksi, $_POST['uid_rfid']);
    $telegram_chat_id = mysqli_real_escape_string($koneksi, $_POST['telegram_chat_id']);
    
    $sql_update = "UPDATE siswa SET nama_lengkap = ?, kelas = ?, uid_rfid = ?, telegram_chat_id = ? WHERE id = ?";
    $stmt_update = mysqli_prepare($koneksi, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "ssssi", $nama_lengkap, $kelas, $uid_rfid, $telegram_chat_id, $id);
    
    if (mysqli_stmt_execute($stmt_update)) {
        $pesan = "Data siswa berhasil diperbarui!";
        $pesan_type = "success";
    } else {
        if (mysqli_errno($koneksi) == 1062) {
            $pesan = "Error: UID RFID sudah digunakan oleh siswa lain.";
            $pesan_type = "error";
        } else {
            $pesan = "Gagal memperbarui data siswa: " . mysqli_error($koneksi);
            $pesan_type = "error";
        }
    }
    mysqli_stmt_close($stmt_update);
}

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
$kelas_filter = isset($_GET['kelas_filter']) ? mysqli_real_escape_string($koneksi, $_GET['kelas_filter']) : '';

// Build query with search and filter
$sql = "SELECT * FROM siswa WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (nama_lengkap LIKE ? OR uid_rfid LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($kelas_filter)) {
    $sql .= " AND kelas = ?";
    $params[] = $kelas_filter;
    $types .= "s";
}

$sql .= " ORDER BY nama_lengkap ASC";

$stmt = mysqli_prepare($koneksi, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get all classes for filter dropdown
$sql_kelas = "SELECT DISTINCT kelas FROM siswa ORDER BY kelas";
$result_kelas = mysqli_query($koneksi, $sql_kelas);

// Count total students
$sql_count = "SELECT COUNT(*) as total FROM siswa";
$result_count = mysqli_query($koneksi, $sql_count);
$total_siswa = mysqli_fetch_assoc($result_count)['total'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Siswa - Absensi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fade-in 0.4s ease-out;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>

<body class="relative min-h-screen bg-gradient-to-br from-blue-50 to-blue-200 flex flex-col font-sans">
    <!-- Navbar -->
    <nav class="bg-blue-800 bg-opacity-80 text-white shadow-lg sticky top-0 z-20 backdrop-blur-md">
        <div class="max-w-6xl mx-auto flex justify-between items-center px-6 py-4">
            <div class="flex items-center gap-3">
                <img src="foto-pondok.jpg" alt="Logo"
                    class="w-10 h-10 rounded-full border-2 border-white shadow-md object-cover" />
                <span class="text-2xl font-bold drop-shadow">Pondok Pesantren Yati</span>
            </div>
            <ul class="flex gap-6 text-lg">
                <li><a href="index.php" class="hover:text-yellow-300 font-medium transition">Absensi</a></li>
                <li><a href="register.php" class="hover:text-yellow-300 font-medium transition">Registrasi</a></li>
                <li><a href="laporan.php" class="hover:text-yellow-300 font-medium transition">Laporan</a></li>
                <li><a href="dashboard.php" class="hover:text-yellow-300 font-medium transition">Dashboard</a></li>
                <li><a href="kelola_siswa.php" class="hover:text-yellow-300 font-medium transition underline">Kelola Siswa</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow p-6">
        <div class="max-w-7xl mx-auto">
            <div class="bg-white bg-opacity-90 rounded-3xl shadow-2xl p-10 fade-in backdrop-blur-md border border-blue-200">
                <div class="flex justify-between items-center mb-8">
                    <h3 class="text-3xl font-bold text-blue-800 drop-shadow">Kelola Data Siswa</h3>
                    <div class="bg-blue-100 px-6 py-3 rounded-xl">
                        <span class="text-blue-800 font-semibold text-lg">Total Siswa: <?php echo $total_siswa; ?></span>
                    </div>
                </div>

                <?php if ($pesan): ?>
                    <div class="mb-6 p-4 rounded-xl text-lg font-semibold border <?php echo 'alert-' . $pesan_type; ?> fade-in">
                        <?php echo $pesan; ?>
                    </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <div class="bg-blue-50 p-6 rounded-2xl shadow-inner border border-blue-200 mb-8">
                    <form method="GET" action="kelola_siswa.php" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <div>
                            <label for="search" class="block font-medium text-blue-900 mb-1">Cari Siswa:</label>
                            <input type="text" id="search" name="search" placeholder="Nama atau UID RFID..."
                                value="<?php echo htmlspecialchars($search); ?>"
                                class="w-full border-2 border-blue-300 rounded-xl py-2 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm" />
                        </div>
                        <div>
                            <label for="kelas_filter" class="block font-medium text-blue-900 mb-1">Filter Kelas:</label>
                            <select id="kelas_filter" name="kelas_filter"
                                class="w-full border-2 border-blue-300 rounded-xl py-2 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm">
                                <option value="">Semua Kelas</option>
                                <?php mysqli_data_seek($result_kelas, 0); ?>
                                <?php while ($row_kelas = mysqli_fetch_assoc($result_kelas)): ?>
                                    <option value="<?php echo htmlspecialchars($row_kelas['kelas']); ?>" 
                                        <?php echo ($kelas_filter == $row_kelas['kelas']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($row_kelas['kelas']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit"
                                class="py-2 px-6 rounded-xl bg-blue-700 text-white font-bold text-lg shadow hover:bg-blue-800 transition transform hover:scale-105">
                                Cari
                            </button>
                            <a href="kelola_siswa.php"
                                class="py-2 px-6 rounded-xl bg-gray-500 text-white font-bold text-lg shadow hover:bg-gray-600 transition transform hover:scale-105">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Students Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full border border-blue-200 rounded-xl overflow-hidden shadow-lg">
                        <thead class="bg-blue-700 text-white">
                            <tr>
                                <th class="py-3 px-4 font-semibold text-center">No.</th>
                                <th class="py-3 px-4 font-semibold text-left">Nama Lengkap</th>
                                <th class="py-3 px-4 font-semibold text-center">Kelas</th>
                                <th class="py-3 px-4 font-semibold text-center">UID RFID</th>
                                <th class="py-3 px-4 font-semibold text-center">Telegram Chat ID</th>
                                <th class="py-3 px-4 font-semibold text-center">Tanggal Daftar</th>
                                <th class="py-3 px-4 font-semibold text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white text-blue-900">
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php $no = 1; ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr class="hover:bg-blue-100 transition">
                                        <td class="py-3 px-4 text-center"><?php echo $no++; ?></td>
                                        <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                                        <td class="py-3 px-4 text-center"><?php echo htmlspecialchars($row['kelas']); ?></td>
                                        <td class="py-3 px-4 text-center font-mono text-sm bg-gray-100 rounded px-2">
                                            <?php echo htmlspecialchars($row['uid_rfid']); ?>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <?php echo $row['telegram_chat_id'] ? htmlspecialchars($row['telegram_chat_id']) : '<span class="text-gray-400">-</span>'; ?>
                                        </td>
                                        <td class="py-3 px-4 text-center text-sm">
                                            <?php echo date('d-m-Y', strtotime($row['created_at'])); ?>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <div class="flex justify-center gap-2">
                                                <button onclick="editSiswa(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                                    class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded-lg text-sm font-medium transition">
                                                    Edit
                                                </button>
                                                <button onclick="deleteSiswa(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nama_lengkap']); ?>')"
                                                    class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg text-sm font-medium transition">
                                                    Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="py-8 px-4 text-center text-gray-500 text-lg">
                                        <?php if (!empty($search) || !empty($kelas_filter)): ?>
                                            Tidak ada data siswa yang sesuai dengan kriteria pencarian.
                                        <?php else: ?>
                                            Belum ada data siswa yang terdaftar.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-8">
            <h4 class="text-2xl font-bold text-blue-800 mb-6">Edit Data Siswa</h4>
            <form id="editForm" method="POST">
                <input type="hidden" id="edit_id" name="id">
                <div class="space-y-4">
                    <div>
                        <label for="edit_nama_lengkap" class="block font-medium text-blue-900 mb-1">Nama Lengkap</label>
                        <input type="text" id="edit_nama_lengkap" name="nama_lengkap" required
                            class="w-full border-2 border-blue-300 rounded-xl py-3 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm" />
                    </div>
                    <div>
                        <label for="edit_kelas" class="block font-medium text-blue-900 mb-1">Kelas</label>
                        <input type="text" id="edit_kelas" name="kelas" required
                            class="w-full border-2 border-blue-300 rounded-xl py-3 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm" />
                    </div>
                    <div>
                        <label for="edit_uid_rfid" class="block font-medium text-blue-900 mb-1">UID RFID</label>
                        <input type="text" id="edit_uid_rfid" name="uid_rfid" required
                            class="w-full border-2 border-blue-300 rounded-xl py-3 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm" />
                    </div>
                    <div>
                        <label for="edit_telegram_chat_id" class="block font-medium text-blue-900 mb-1">Telegram Chat ID</label>
                        <input type="text" id="edit_telegram_chat_id" name="telegram_chat_id"
                            class="w-full border-2 border-blue-300 rounded-xl py-3 px-4 text-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-500 bg-white shadow-sm" />
                    </div>
                </div>
                <div class="flex gap-4 mt-8">
                    <button type="submit" name="update_siswa"
                        class="flex-1 py-3 rounded-xl bg-blue-700 text-white font-bold text-lg shadow hover:bg-blue-800 transition">
                        Simpan Perubahan
                    </button>
                    <button type="button" onclick="closeEditModal()"
                        class="flex-1 py-3 rounded-xl bg-gray-500 text-white font-bold text-lg shadow hover:bg-gray-600 transition">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-8">
            <h4 class="text-2xl font-bold text-red-800 mb-4">Konfirmasi Hapus</h4>
            <p class="text-gray-700 mb-6">Apakah Anda yakin ingin menghapus siswa <strong id="delete_nama"></strong>?</p>
            <p class="text-sm text-red-600 mb-6">
                <strong>Peringatan:</strong> Semua data absensi siswa ini juga akan terhapus!
            </p>
            <form id="deleteForm" method="POST">
                <input type="hidden" id="delete_id" name="id">
                <div class="flex gap-4">
                    <button type="submit" name="delete_siswa"
                        class="flex-1 py-3 rounded-xl bg-red-600 text-white font-bold text-lg shadow hover:bg-red-700 transition">
                        Ya, Hapus
                    </button>
                    <button type="button" onclick="closeDeleteModal()"
                        class="flex-1 py-3 rounded-xl bg-gray-500 text-white font-bold text-lg shadow hover:bg-gray-600 transition">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editSiswa(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_nama_lengkap').value = data.nama_lengkap;
            document.getElementById('edit_kelas').value = data.kelas;
            document.getElementById('edit_uid_rfid').value = data.uid_rfid;
            document.getElementById('edit_telegram_chat_id').value = data.telegram_chat_id || '';
            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        function deleteSiswa(id, nama) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_nama').textContent = nama;
            document.getElementById('deleteModal').classList.add('show');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        });
    </script>
</body>

</html>
<?php
mysqli_stmt_close($stmt);
mysqli_close($koneksi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Customer Baru</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f6f8; padding: 20px;">

    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table width="600" cellpadding="20" cellspacing="0" style="background-color: #ffffff; border-radius: 8px;">

                    <!-- HEADER -->
                    <tr>
                        <td style="text-align: center; background-color: #1d4ed8; color: #ffffff; border-radius: 8px 8px 0 0;">
                            <h2 style="margin: 0;">🔍 Customer Baru</h2>
                        </td>
                    </tr>

                    <!-- CONTENT -->
                    <tr>
                        <td>
                            <p>Halo Auditor,</p>

                            <p>
                                Terdapat <strong>customer baru</strong> yang telah disubmit dan memerlukan pengecekan Anda.
                            </p>

                            <hr>

                            <h4>📌 Informasi Customer</h4>
                            <table width="100%" cellpadding="5" cellspacing="0">
                                <tr>
                                    <td><strong>Nama</strong></td>
                                    <td>: {{ $customer->nama_perusahaan ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Alamat</strong></td>
                                    <td>: {{ $customer->alamat_lengkap ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Kota</strong></td>
                                    <td>: {{ $customer->kota ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>PIC</strong></td>
                                    <td>: {{ $customer->nama_personal ?? '-' }} ({{ $customer->no_telp_personal ?? '-' }})</td>
                                </tr>
                            </table>

                            <hr>

                            <h4>👤 Disubmit oleh</h4>
                            <table width="100%" cellpadding="5" cellspacing="0">
                                <tr>
                                    <td><strong>Nama User</strong></td>
                                    <td>: {{ $user->name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Email</strong></td>
                                    <td>: {{ $user->email ?? '-' }}</td>
                                </tr>
                            </table>

                            <hr>

                            <p>
                                Silakan lakukan pengecekan melalui sistem.
                            </p>

                            <!-- BUTTON -->
                            <p style="text-align: center; margin-top: 20px;">
                                <a href="{{ route('customer.show', $customer->id) }}"
                                   style="background-color: #1d4ed8; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                                    🔎 Lihat Customer
                                </a>
                            </p>

                            <p style="margin-top: 30px;">
                                Terima kasih,<br>
                                <strong>Sistem</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style="text-align: center; font-size: 12px; color: #888;">
                            Email ini dikirim otomatis oleh sistem. Mohon tidak membalas email ini.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
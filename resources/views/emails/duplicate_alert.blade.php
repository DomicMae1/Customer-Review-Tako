<!DOCTYPE html>
<html>

<head>
    <title>Alert Duplikat</title>
</head>

<body>
    <h2 style="color:red;">Peringatan: input data customer dengan catatan bermasalah</h2>
    <p>User <b>{{ $user->name }}</b> baru saja memasukkan data customer yang bernama <b>{{ $newCustomer->nama_perusahaan ?? '-' }}</b> dengan nomor NPWP <b>{{ $newCustomer->no_npwp }}</b> mendapatkan review bermasalah dari perusahaan <b>{{ $problemCustomer->perusahaan->nama_perusahaan ?? '-' }}</b>.</p>

    @if($status->status_3_keterangan)
    <p><b>👨‍⚖️ Catatan Lawyer:</b><br>
        "{{ $status->status_3_keterangan }}"</p>
    @endif

    @if($status->status_4_keterangan)
    <p><b>🕵️ Catatan Auditor:</b><br>
        "{{ $status->status_4_keterangan }}"</p>
    @endif

    <p><i>Silakan cek lampiran email ini untuk file pendukung.</i></p>
</body>

</html>
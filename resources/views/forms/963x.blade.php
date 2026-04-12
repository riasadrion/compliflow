<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Form 963X — Pre-ETS Service Log</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 40px; }
        h1 { font-size: 16px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 6px 10px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>NYS ACCES-VR — Pre-ETS Service Log (963X)</h1>
    <table>
        <thead>
            <tr>
                <th>Client Name</th>
                <th>DOB</th>
                <th>Service Date</th>
                <th>Auth Number</th>
                <th>Hours</th>
                <th>Service Code</th>
                <th>Signature</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $row)
            <tr>
                <td>{{ $row['client_name'] }}</td>
                <td>{{ $row['dob'] }}</td>
                <td>{{ $row['service_date'] }}</td>
                <td>{{ $row['authorization_number'] }}</td>
                <td>{{ $row['hours'] }}</td>
                <td>{{ $row['service_code'] }}</td>
                <td>{{ $row['signature_present'] ? 'Yes' : 'No' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Form 964X — WBLE Payroll Record</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 40px; }
        h1 { font-size: 16px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 6px 10px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>NYS ACCES-VR — WBLE Payroll Record (964X)</h1>
    <table>
        <thead>
            <tr>
                <th>Client Name</th>
                <th>DOB</th>
                <th>Employer</th>
                <th>Wage Rate</th>
                <th>Hours Worked</th>
                <th>Pay Date</th>
                <th>Signature</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $row)
            <tr>
                <td>{{ $row['client_name'] }}</td>
                <td>{{ $row['dob'] }}</td>
                <td>{{ $row['employer'] }}</td>
                <td>${{ number_format($row['wage_rate'], 2) }}</td>
                <td>{{ $row['hours_worked'] }}</td>
                <td>{{ $row['pay_date'] }}</td>
                <td>{{ $row['signature'] ? 'Yes' : 'No' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

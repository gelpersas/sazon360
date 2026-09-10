<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Listado de ventas</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 15px; margin-bottom: 2px; }
        .subtitulo { color: #555; margin-top: 0; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 5px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f2f2f2; }
        .num { text-align: right; }
        tfoot td { border-top: 2px solid #333; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $empresa->nombre_comercial ?: $empresa->nombre }}</h1>
    <p class="subtitulo">Listado de ventas — generado {{ now()->format('d/m/Y H:i') }} — {{ $ventas->count() }} {{ \Illuminate\Support\Str::plural('venta', $ventas->count()) }}</p>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Sede</th>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Mesa</th>
                <th>Cliente</th>
                <th>Atendido por</th>
                <th>Estado</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ventas as $venta)
                <tr>
                    <td>{{ $venta->id }}</td>
                    <td>{{ $venta->sede->nombre }}</td>
                    <td>{{ ($venta->cerrado_at ?? $venta->created_at)->format('d/m/Y H:i') }}</td>
                    <td>{{ $venta->tipo->getLabel() }}</td>
                    <td>{{ $venta->mesa->nombre ?? '—' }}</td>
                    <td>{{ $venta->cliente->nombre ?? '—' }}</td>
                    <td>{{ $venta->usuario->name }}</td>
                    <td>{{ $venta->estado->getLabel() }}</td>
                    <td class="num">${{ $venta->total() }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8">Total consolidado</td>
                <td class="num">${{ $totalConsolidado }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>

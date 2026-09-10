<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo #{{ $pedido->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 0; }
        .subtitulo { color: #555; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 4px 6px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f2f2f2; }
        .num { text-align: right; }
        .total-row td { border-top: 2px solid #333; border-bottom: none; font-weight: bold; }
        .datos { margin-top: 10px; }
        .datos td { border: none; padding: 1px 6px 1px 0; }
    </style>
</head>
<body>
    <h1>{{ $pedido->empresa->nombre_comercial ?: $pedido->empresa->nombre }}</h1>
    <p class="subtitulo">{{ $pedido->sede->nombre }} — Recibo #{{ $pedido->id }}</p>

    <table class="datos">
        <tr>
            <td><strong>Fecha:</strong></td>
            <td>{{ ($pedido->cerrado_at ?? $pedido->created_at)->format('d/m/Y H:i') }}</td>
            <td><strong>Tipo:</strong></td>
            <td>{{ $pedido->tipo->getLabel() }}{{ $pedido->mesa ? " — {$pedido->mesa->nombre}" : '' }}</td>
        </tr>
        <tr>
            <td><strong>Atendido por:</strong></td>
            <td>{{ $pedido->usuario->name }}</td>
            <td><strong>Cliente:</strong></td>
            <td>{{ $pedido->cliente->nombre ?? '—' }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th class="num">Cant.</th>
                <th class="num">Precio</th>
                <th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pedido->items as $item)
                <tr>
                    <td>{{ $item->nombre_producto }}</td>
                    <td class="num">{{ $item->cantidad }}</td>
                    <td class="num">${{ number_format((float) $item->precio_unitario, 2) }}</td>
                    <td class="num">${{ $item->subtotal() }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3">Total</td>
                <td class="num">${{ $pedido->total() }}</td>
            </tr>
        </tbody>
    </table>

    @if ($pedido->pagos->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>Medio de pago</th>
                    <th class="num">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pedido->pagos as $pago)
                    <tr>
                        <td>{{ $pago->medio->getLabel() }}</td>
                        <td class="num">${{ number_format((float) $pago->monto, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>

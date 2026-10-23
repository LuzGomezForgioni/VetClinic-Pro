<?php

/**
 * Generador PDF simple para reportes de VetClinic Pro.
 * Usa fuentes estándar de PDF y no necesita Composer.
 */
class PdfSimple
{
    private const ANCHO = 595.28;
    private const ALTO = 841.89;

    private array $paginas = [];
    private int $paginaActual = -1;
    private ?array $logo = null;

    public function __construct(?string $rutaLogo = null)
    {
        if ($rutaLogo) {
            $this->cargarLogo($rutaLogo);
        }

        $this->agregarPagina();
    }

    private function cargarLogo(string $ruta): void
    {
        if (!is_file($ruta)) {
            return;
        }

        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $datos = null;

        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            $datos = @file_get_contents($ruta);
        } elseif (
            $extension === 'png' &&
            function_exists('imagecreatefrompng') &&
            function_exists('imagejpeg')
        ) {
            $imagen = @imagecreatefrompng($ruta);

            if ($imagen !== false) {
                $fondo = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
                $blanco = imagecolorallocate($fondo, 255, 255, 255);
                imagefill($fondo, 0, 0, $blanco);
                imagecopy(
                    $fondo,
                    $imagen,
                    0,
                    0,
                    0,
                    0,
                    imagesx($imagen),
                    imagesy($imagen)
                );
                ob_start();
                imagejpeg($fondo, null, 88);
                $datos = ob_get_clean();
                imagedestroy($fondo);
                imagedestroy($imagen);
            }
        }

        if (!$datos) {
            return;
        }

        $info = @getimagesizefromstring($datos);

        if (!$info || empty($info[0]) || empty($info[1])) {
            return;
        }

        $this->logo = [
            'bytes' => $datos,
            'width' => (int) $info[0],
            'height' => (int) $info[1]
        ];
    }

    public function agregarPagina(): void
    {
        $this->paginas[] = '';
        $this->paginaActual = count($this->paginas) - 1;
    }

    private function agregarComando(string $comando): void
    {
        $this->paginas[$this->paginaActual] .= $comando . "\n";
    }

    private function yPdf(float $yDesdeArriba): float
    {
        return self::ALTO - $yDesdeArriba;
    }

    private function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 3, '.', ''), '0'), '.');
    }

    private function textoPdf(string $texto): string
    {
        $convertido = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $texto);

        if ($convertido === false) {
            $convertido = $texto;
        }

        return str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\\(', '\\)', '', ' '],
            $convertido
        );
    }

    public function colorTexto(int $r, int $g, int $b): void
    {
        $this->agregarComando(sprintf(
            '%.3f %.3f %.3f rg',
            $r / 255,
            $g / 255,
            $b / 255
        ));
    }

    public function colorRelleno(int $r, int $g, int $b): void
    {
        $this->agregarComando(sprintf(
            '%.3f %.3f %.3f rg',
            $r / 255,
            $g / 255,
            $b / 255
        ));
    }

    public function colorLinea(int $r, int $g, int $b): void
    {
        $this->agregarComando(sprintf(
            '%.3f %.3f %.3f RG',
            $r / 255,
            $g / 255,
            $b / 255
        ));
    }

    public function texto(
        float $x,
        float $y,
        string $texto,
        float $tamano = 10,
        bool $negrita = false
    ): void {
        $fuente = $negrita ? 'F2' : 'F1';
        $this->agregarComando(
            'BT /' . $fuente . ' ' . $this->numero($tamano) .
            ' Tf ' . $this->numero($x) . ' ' .
            $this->numero($this->yPdf($y)) .
            ' Td (' . $this->textoPdf($texto) . ') Tj ET'
        );
    }

    public function rectangulo(
        float $x,
        float $y,
        float $ancho,
        float $alto,
        bool $rellenar = true
    ): void {
        $operador = $rellenar ? 'f' : 'S';
        $this->agregarComando(
            $this->numero($x) . ' ' .
            $this->numero($this->yPdf($y + $alto)) . ' ' .
            $this->numero($ancho) . ' ' .
            $this->numero($alto) . ' re ' . $operador
        );
    }

    public function linea(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $grosor = 1
    ): void {
        $this->agregarComando(
            $this->numero($grosor) . ' w ' .
            $this->numero($x1) . ' ' .
            $this->numero($this->yPdf($y1)) . ' m ' .
            $this->numero($x2) . ' ' .
            $this->numero($this->yPdf($y2)) . ' l S'
        );
    }

    public function imagenLogo(
        float $x,
        float $y,
        float $ancho = 34
    ): bool {
        if ($this->logo === null) {
            return false;
        }

        $alto = $ancho * ($this->logo['height'] / $this->logo['width']);
        $yInferior = self::ALTO - $y - $alto;

        $this->agregarComando(
            'q ' . $this->numero($ancho) . ' 0 0 ' .
            $this->numero($alto) . ' ' .
            $this->numero($x) . ' ' .
            $this->numero($yInferior) . ' cm /Im1 Do Q'
        );

        return true;
    }

    public function envolverTexto(
        string $texto,
        float $ancho,
        float $tamano = 10
    ): array {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);

        if ($texto === '') {
            return ['—'];
        }

        $palabras = preg_split('/\s+/u', $texto) ?: [];
        $lineas = [];
        $linea = '';
        $maxCaracteres = max(8, (int) floor($ancho / ($tamano * 0.52)));

        foreach ($palabras as $palabra) {
            $candidata = $linea === '' ? $palabra : $linea . ' ' . $palabra;

            if (mb_strlen($candidata) <= $maxCaracteres) {
                $linea = $candidata;
                continue;
            }

            if ($linea !== '') {
                $lineas[] = $linea;
            }

            $linea = $palabra;
        }

        if ($linea !== '') {
            $lineas[] = $linea;
        }

        return $lineas ?: ['—'];
    }

    public function parrafo(
        float $x,
        float &$y,
        string $texto,
        float $ancho,
        float $tamano = 9,
        float $interlineado = 12,
        bool $negrita = false
    ): void {
        foreach ($this->envolverTexto($texto, $ancho, $tamano) as $linea) {
            if ($y > 790) {
                $this->agregarPagina();
                $y = 55;
            }

            $this->texto($x, $y, $linea, $tamano, $negrita);
            $y += $interlineado;
        }
    }

    public function obtenerAncho(): float
    {
        return self::ANCHO;
    }

    public function obtenerAlto(): float
    {
        return self::ALTO;
    }

    public function salida(string $nombreArchivo): void
    {
        $objetos = [];
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $siguienteObjeto = 5;
        $objetoImagen = null;

        if ($this->logo !== null) {
            $objetoImagen = $siguienteObjeto++;
            $bytesImagen = $this->logo['bytes'];
            $objetos[$objetoImagen] =
                '<< /Type /XObject /Subtype /Image' .
                ' /Width ' . $this->logo['width'] .
                ' /Height ' . $this->logo['height'] .
                ' /ColorSpace /DeviceRGB /BitsPerComponent 8' .
                ' /Filter /DCTDecode /Length ' . strlen($bytesImagen) .
                " >>\nstream\n" . $bytesImagen . "\nendstream";
        }

        $referenciasPaginas = [];

        foreach ($this->paginas as $contenido) {
            $objetoPagina = $siguienteObjeto++;
            $objetoContenido = $siguienteObjeto++;
            $referenciasPaginas[] = $objetoPagina . ' 0 R';

            $recursos = '<< /Font << /F1 3 0 R /F2 4 0 R >>';

            if ($objetoImagen !== null) {
                $recursos .= ' /XObject << /Im1 ' . $objetoImagen . ' 0 R >>';
            }

            $recursos .= ' >>';

            $objetos[$objetoPagina] =
                '<< /Type /Page /Parent 2 0 R' .
                ' /MediaBox [0 0 ' . self::ANCHO . ' ' . self::ALTO . ']' .
                ' /Resources ' . $recursos .
                ' /Contents ' . $objetoContenido . ' 0 R >>';

            $objetos[$objetoContenido] =
                '<< /Length ' . strlen($contenido) . " >>\nstream\n" .
                $contenido . "endstream";
        }

        $objetos[2] =
            '<< /Type /Pages /Count ' . count($referenciasPaginas) .
            ' /Kids [' . implode(' ', $referenciasPaginas) . '] >>';

        ksort($objetos);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];

        foreach ($objetos as $numero => $contenidoObjeto) {
            $offsets[$numero] = strlen($pdf);
            $pdf .= $numero . " 0 obj\n" . $contenidoObjeto . "\nendobj\n";
        }

        $inicioXref = strlen($pdf);
        $maxObjeto = max(array_keys($objetos));
        $pdf .= "xref\n0 " . ($maxObjeto + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $maxObjeto; $i++) {
            $offset = $offsets[$i] ?? 0;
            $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
        }

        $pdf .= "trailer\n<< /Size " . ($maxObjeto + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $inicioXref . "\n%%EOF";

        $nombreArchivo = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombreArchivo) ?: 'reporte.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Content-Length: ' . strlen($pdf));
        header('X-Content-Type-Options: nosniff');
        echo $pdf;
        exit;
    }
}

function pdfCabecera(
    PdfSimple $pdf,
    string $titulo,
    string $subtitulo = ''
): float {
    $pdf->colorTexto(26, 31, 94);
    $usoLogo = $pdf->imagenLogo(42, 32, 38);

    $xMarca = $usoLogo ? 90 : 42;
    $pdf->texto($xMarca, 48, 'VetClinic Pro', 15, true);
    $pdf->colorTexto(107, 114, 128);
    $pdf->texto($xMarca, 63, 'Gestión veterinaria', 8, false);

    $pdf->colorTexto(26, 31, 94);
    $pdf->texto(42, 100, $titulo, 20, true);

    if ($subtitulo !== '') {
        $pdf->colorTexto(107, 114, 128);
        $pdf->texto(42, 118, $subtitulo, 9, false);
    }

    $pdf->colorLinea(229, 231, 235);
    $pdf->linea(42, 136, 553, 136, 0.7);

    return 158;
}

function pdfPie(PdfSimple $pdf, float $y = 810): void
{
    $pdf->colorTexto(135, 141, 153);
    $pdf->texto(
        42,
        $y,
        'Generado por VetClinic Pro · ' . date('d/m/Y H:i'),
        7
    );
}

function pdfTarjetaMetrica(
    PdfSimple $pdf,
    float $x,
    float $y,
    float $ancho,
    string $etiqueta,
    string $valor,
    string $detalle = ''
): void {
    $pdf->colorRelleno(247, 248, 252);
    $pdf->rectangulo($x, $y, $ancho, 66, true);
    $pdf->colorLinea(229, 231, 235);
    $pdf->rectangulo($x, $y, $ancho, 66, false);
    $pdf->colorTexto(107, 114, 128);
    $pdf->texto($x + 12, $y + 18, $etiqueta, 8, false);
    $pdf->colorTexto(26, 31, 94);
    $pdf->texto($x + 12, $y + 41, $valor, 16, true);

    if ($detalle !== '') {
        $pdf->colorTexto(73, 146, 181);
        $pdf->texto($x + 12, $y + 56, $detalle, 7, false);
    }
}

function pdfTabla(
    PdfSimple $pdf,
    float &$y,
    array $columnas,
    array $filas,
    string $tituloTabla = ''
): void {
    if ($tituloTabla !== '') {
        if ($y > 730) {
            pdfPie($pdf);
            $pdf->agregarPagina();
            $y = pdfCabecera($pdf, $tituloTabla);
        } else {
            $pdf->colorTexto(26, 31, 94);
            $pdf->texto(42, $y, $tituloTabla, 12, true);
            $y += 16;
        }
    }

    $xInicial = 42;
    $altoFila = 22;

    $dibujarCabecera = function () use ($pdf, &$y, $columnas, $xInicial, $altoFila): void {
        $x = $xInicial;
        $pdf->colorRelleno(26, 31, 94);

        foreach ($columnas as $columna) {
            $ancho = (float) $columna['width'];
            $pdf->rectangulo($x, $y, $ancho, $altoFila, true);
            $pdf->colorTexto(255, 255, 255);
            $pdf->texto($x + 5, $y + 14, (string) $columna['title'], 7, true);
            $x += $ancho;
        }

        $y += $altoFila;
    };

    $dibujarCabecera();

    foreach ($filas as $indice => $fila) {
        if ($y > 770) {
            pdfPie($pdf);
            $pdf->agregarPagina();
            $y = 52;
            $dibujarCabecera();
        }

        $x = $xInicial;
        $fondo = $indice % 2 === 0 ? [250, 251, 253] : [255, 255, 255];

        foreach ($columnas as $columna) {
            $ancho = (float) $columna['width'];
            $pdf->colorRelleno($fondo[0], $fondo[1], $fondo[2]);
            $pdf->rectangulo($x, $y, $ancho, $altoFila, true);
            $pdf->colorLinea(236, 238, 243);
            $pdf->rectangulo($x, $y, $ancho, $altoFila, false);

            $valor = (string) ($fila[$columna['key']] ?? '—');
            $maximo = max(5, (int) floor($ancho / 4.2));

            if (mb_strlen($valor) > $maximo) {
                $valor = mb_substr($valor, 0, $maximo - 1) . '…';
            }

            $pdf->colorTexto(65, 71, 89);
            $pdf->texto($x + 5, $y + 14, $valor, 6.7, false);
            $x += $ancho;
        }

        $y += $altoFila;
    }

    if (!$filas) {
        $pdf->colorTexto(107, 114, 128);
        $pdf->texto(42, $y + 18, 'No hay datos para el período seleccionado.', 8);
        $y += 32;
    }
}

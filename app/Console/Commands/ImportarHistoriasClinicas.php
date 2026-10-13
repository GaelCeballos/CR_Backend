<?php

namespace App\Console\Commands;

use App\Enums\AccionAuditoria;
use App\Models\Auditoria;
use App\Models\User;
use App\Services\CalculoClinicoService;
use App\Services\ExpedienteBuilder;
use App\Services\PasswordGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Importa el historial clínico heredado (hoja "BDD Resultados" de un .xlsx) a
 * historias_clinicas. A diferencia de un alta normal, usa el folio de la hoja
 * original tal cual como folio_anual (no recalcula), y conserva filas sin
 * nombre ni CURP mientras tengan algún otro dato capturado.
 */
class ImportarHistoriasClinicas extends Command
{
    /** @var string */
    protected $signature = 'historias:importar {ruta} {--limite=}';

    /** @var string */
    protected $description = 'Importa el historial clínico heredado de un .xlsx (hoja "BDD Resultados") a historias_clinicas.';

    /** Columnas que no se mapean de forma genérica: se resuelven aparte en handle()/payloadDeFila(). */
    private const COLUMNAS_ESPECIALES = [1, 2, 17, 18, 80, 84, 85, 206];

    /** Texto que en la hoja significa "no se capturó nada" (incluye el placeholder de un combo sin responder). */
    private const PLACEHOLDERS = ['', '-', '--', '---', 'NA', 'N/A', 'SELECCIONE'];

    /** Columnas (1-206, excluyendo las especiales) => columna snake_case que entiende ExpedienteBuilder. */
    private const COLUMNAS = [
        3 => 'nombre_completo', 4 => 'puesto', 5 => 'empresa', 6 => 'zona_trabajo',
        7 => 'tipo_examen_motivo', 8 => 'antiguedad_empresa', 9 => 'antiguedad_puesto',
        10 => 'exposicion_poe', 11 => 'nss', 12 => 'curp', 13 => 'genero', 14 => 'area',
        15 => 'tipo_examen_categoria', 16 => 'fecha_nacimiento',
        19 => 'domicilio_calle', 20 => 'domicilio_colonia', 21 => 'domicilio_municipio',
        22 => 'domicilio_estado', 23 => 'telefono', 24 => 'originario', 25 => 'religion',
        26 => 'estado_civil',
        27 => 'ahf_diabetes', 28 => 'ahf_hipertension', 29 => 'ahf_cardiopatia',
        30 => 'ahf_neoplasias', 31 => 'ahf_infecciosa', 32 => 'ahf_otras',
        33 => 'ahf_abuelos_paternos', 34 => 'ahf_abuelos_maternos', 35 => 'ahf_padre',
        36 => 'ahf_madre', 37 => 'ahf_hermanos', 38 => 'ahf_hijos', 39 => 'escolaridad',
        40 => 'vivienda', 41 => 'alimentacion', 42 => 'convivencia_animales',
        43 => 'actividad_fisica', 44 => 'inmunizaciones', 45 => 'tatuajes',
        46 => 't_actual_empresa', 47 => 't_actual_puesto', 48 => 't_actual_tiempo',
        49 => 't_actual_condiciones', 50 => 't_penultimo_empresa', 51 => 't_penultimo_puesto',
        52 => 't_penultimo_tiempo', 53 => 't_penultimo_condiciones', 54 => 't_antepenultimo_empresa',
        55 => 't_antepenultimo_puesto', 56 => 't_antepenultimo_tiempo', 57 => 't_antepenultimo_condiciones',
        58 => 'enfermedades_profesionales',
        59 => 'etilismo', 60 => 'tabaquismo', 61 => 'adicciones', 62 => 'alergias',
        63 => 'quirurgicos', 64 => 'traumatismos', 65 => 'hemotransfusiones', 66 => 'app_hta',
        67 => 'app_dm', 68 => 'app_cardiaca', 69 => 'app_respiratoria', 70 => 'app_renal',
        71 => 'app_musculo_esqueletica', 72 => 'app_lumbar', 73 => 'app_acido_peptica',
        74 => 'app_otras',
        75 => 'pulso', 76 => 'respiracion', 77 => 'temperatura', 78 => 'ta_sistolica',
        79 => 'ta_diastolica', 81 => 'clasificacion_aha', 82 => 'peso', 83 => 'talla',
        86 => 'constitucion', 87 => 'piel_faneras', 88 => 'torax_obs', 89 => 'torax_pulmones',
        90 => 'torax_corazon', 91 => 'abdomen_obs', 92 => 'abdomen_higado',
        93 => 'abdomen_hernias', 94 => 'abdomen_hemorroides', 95 => 'abdomen_bazo',
        96 => 'vl_od', 97 => 'vl_oi', 98 => 'vl_con_lentes', 99 => 'vl_resultado',
        100 => 'vc_od', 101 => 'vc_oi', 102 => 'vc_con_lentes', 103 => 'vc_resultado',
        104 => 'reflejos_oculares_od', 105 => 'reflejos_oculares_oi', 106 => 'pterigion_od',
        107 => 'pterigion_oi', 108 => 'catarata_od', 109 => 'catarata_oi', 110 => 'estrabismo',
        111 => 'ishihara', 112 => 'oftal_recomendaciones', 113 => 'lentes_seguridad',
        114 => 'canal_auditivo_od', 115 => 'canal_auditivo_oi', 116 => 'membrana_timpanica_d',
        117 => 'membrana_timpanica_i', 118 => 'nariz_obs', 119 => 'nariz_obstrucciones',
        120 => 'orofaringe', 121 => 'dentadura', 122 => 'cuello', 123 => 'gu_obs',
        124 => 'gu_hernias', 125 => 'gu_hidrocele', 126 => 'gu_varicocele',
        127 => 'ext_sup_obs', 128 => 'ext_sup_manos', 129 => 'ext_inf_obs',
        130 => 'ext_inf_hallux_valgus', 131 => 'reflejos_osteotendinosos', 132 => 'psiquismo',
        133 => 'padecimiento_actual',
        134 => 'qs_glucosa', 135 => 'qs_urea', 136 => 'qs_creatinina', 137 => 'qs_acido_urico',
        138 => 'qs_colesterol', 139 => 'qs_trigliceridos', 140 => 'qs_albumina',
        141 => 'qs_bilirrubina_total', 142 => 'qs_ast', 143 => 'qs_fosfatasa_alcalina',
        144 => 'qs_dhl', 145 => 'qs_hba1c', 146 => 'bh_grupo_rh', 147 => 'bh_leucocitos',
        148 => 'bh_eritrocitos', 149 => 'bh_hemoglobina', 150 => 'bh_hematocrito',
        151 => 'bh_vcm', 152 => 'bh_mch', 153 => 'bh_mchc', 154 => 'bh_plaquetas',
        155 => 'bh_mpv', 156 => 'bh_neutrofilos', 157 => 'bh_linfocitos_pct',
        158 => 'bh_monocitos', 159 => 'bh_linfocitos_abs',
        160 => 'ad_opi', 161 => 'ad_coc', 162 => 'ad_thc', 163 => 'ad_bzo', 164 => 'ad_met',
        165 => 'ad_amp',
        166 => 'ego_color', 167 => 'ego_aspecto', 168 => 'ego_densidad', 169 => 'ego_ph',
        170 => 'ego_nitritos', 171 => 'ego_proteinas', 172 => 'ego_glucosa',
        173 => 'ego_cetonas', 174 => 'ego_bilirrubina', 175 => 'ego_urobilinogeno',
        176 => 'ego_hemoglobina', 177 => 'ego_leucocitos', 178 => 'ego_eritrocitos',
        179 => 'ego_cristales',
        180 => 'rx_torax', 181 => 'rx_lumbar',
        182 => 'aud150_od', 183 => 'aud150_oi', 184 => 'aud250_od', 185 => 'aud250_oi',
        186 => 'aud500_od', 187 => 'aud500_oi', 188 => 'aud1000_od', 189 => 'aud1000_oi',
        190 => 'aud2000_od', 191 => 'aud2000_oi', 192 => 'aud3000_od', 193 => 'aud3000_oi',
        194 => 'aud4000_od', 195 => 'aud4000_oi', 196 => 'aud6000_od', 197 => 'aud6000_oi',
        198 => 'aud8000_od', 199 => 'aud8000_oi',
        200 => 'audiometria_resultado', 201 => 'ecg', 202 => 'espirometria',
        203 => 'valoracion_oftalmologica', 204 => 'diagnostico', 205 => 'gradiente',
    ];

    /** Columnas de audiometría (182-199): mismo rango -10..120 que el CHECK de hc_audiometria. */
    private const COLUMNAS_AUDIOMETRIA = [182, 183, 184, 185, 186, 187, 188, 189, 190, 191, 192, 193, 194, 195, 196, 197, 198, 199];

    /** Columnas "numeric" de hc_exploracion sin rango propio (solo deben ser numéricas o null). */
    private const COLUMNAS_NUMERICAS_LIBRES = [75, 76, 77, 78, 79, 83];

    public function __construct(
        private ExpedienteBuilder $builder,
        private CalculoClinicoService $calculos,
    ) {
        parent::__construct();
    }

    /**
     * Punto de entrada del comando: valida el archivo, prepara las cuentas de
     * los médicos involucrados, recorre la hoja "BDD Resultados" fila por
     * fila y crea un expediente por cada fila con datos reales.
     *
     * @return int código de salida (self::SUCCESS o self::FAILURE)
     */
    public function handle(): int
    {
        $ruta = $this->argument('ruta');

        if (! is_file($ruta)) {
            $this->error("No existe el archivo: {$ruta}");

            return self::FAILURE;
        }

        $limite = $this->option('limite') !== null ? (int) $this->option('limite') : null;

        ini_set('memory_limit', '1024M');

        $admin = User::where('rol', 'admin')->first();

        if (! $admin) {
            $this->error('No existe ninguna cuenta admin; corre antes "php artisan db:seed".');

            return self::FAILURE;
        }

        $coria = User::where('email', 'manuel.coria@crm-colima.mx')->first();
        $gonzalez = $this->cuentaMedico('mariano.gonzalez@crm-colima.mx', 'MARIANO ALEJANDRO GONZÁLEZ CUENCA', $admin);
        $villavicencio = $this->cuentaMedico('evelyn.villavicencio@crm-colima.mx', 'EVELYN CHANTAL VILLAVICENCIO RAMOS', $admin);

        $this->info('Leyendo el archivo (puede tardar un poco)...');
        $spreadsheet = IOFactory::load($ruta);
        $hoja = $spreadsheet->getSheetByName('BDD Resultados');

        if (! $hoja) {
            $this->error('No se encontró la hoja "BDD Resultados".');

            return self::FAILURE;
        }

        $maxFila = $hoja->getHighestDataRow();
        $importados = 0;
        $omitidosVacios = 0;
        $errores = [];

        for ($fila = 6; $fila <= $maxFila; $fila++) {
            $folioHoja = $hoja->getCell([1, $fila])->getCalculatedValue();

            if ($folioHoja === null || $folioHoja === '') {
                continue;
            }

            $crudo = [];
            for ($col = 2; $col <= 206; $col++) {
                $crudo[$col] = $hoja->getCell([$col, $fila])->getCalculatedValue();
            }

            if ($this->filaVacia($crudo)) {
                $omitidosVacios++;

                continue;
            }

            if ($limite !== null && $importados >= $limite) {
                break;
            }

            try {
                $fechaEstudios = $this->fechaDeCelda($crudo[2] ?? null);
                $payload = $this->payloadDeFila($crudo, $gonzalez, $villavicencio, $coria);

                $this->builder->importar($payload, $admin, (int) $folioHoja, $fechaEstudios);
                $importados++;
            } catch (\Throwable $e) {
                $errores[] = "Fila {$fila} (folio hoja {$folioHoja}): {$e->getMessage()}";
            }

            if ($importados > 0 && $importados % 200 === 0) {
                $this->info("Importados: {$importados}...");
            }
        }

        $this->registrarResumenBitacora($admin, $importados, $omitidosVacios, count($errores));

        $this->info("Listo. Importados: {$importados}. Omitidos (sin datos capturados): {$omitidosVacios}. Errores: ".count($errores));

        foreach (array_slice($errores, 0, 50) as $linea) {
            $this->warn($linea);
        }

        if (count($errores) > 50) {
            $this->warn('... y '.(count($errores) - 50).' errores más (no se muestran todos).');
        }

        return self::SUCCESS;
    }

    /**
     * Busca (o crea, con contraseña temporal) la cuenta de uno de los 2
     * médicos adicionales encontrados en la hoja. La cédula/universidad son
     * un marcador visible ("PENDIENTE"/"POR CONFIRMAR"): el admin las
     * corrige después desde el panel de usuarios.
     *
     * @param  string  $email  correo de la cuenta
     * @param  string  $nombreCompleto  nombre completo en mayúsculas
     * @param  User  $creadoPor  cuenta a la que se le atribuye la creación (admin)
     * @return User la cuenta encontrada o recién creada
     */
    private function cuentaMedico(string $email, string $nombreCompleto, User $creadoPor): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'hashed_password' => Hash::make((new PasswordGeneratorService)->generar()),
                'nombre_completo' => $nombreCompleto,
                'cedula_profesional' => 'PENDIENTE',
                'universidad' => 'POR CONFIRMAR',
                'rol' => 'servicios_medicos',
                'estado' => 'activo',
                'is_temporary_password' => true,
                'creado_por' => $creadoPor->id,
            ],
        );
    }

    /**
     * Columnas que la propia hoja rellena con un valor por defecto aunque la
     * fila esté vacía, asi que no cuentan como "dato real capturado": 17/18
     * (edad/grupo de edad) y 80/84/85 (TA conjunta/IMC/estado nutricional) se
     * calculan solos; 160-165 (antidoping) venían precargados en "NO REACTIVO"
     * incluso sin que se hiciera ningún examen.
     */
    private const COLUMNAS_CON_DEFECTO_DE_PLANTILLA = [17, 18, 80, 84, 85, 160, 161, 162, 163, 164, 165];

    /**
     * Decide si una fila no tiene ningún dato real capturado (solo el folio
     * reservado): ignora las columnas que la propia hoja rellena con un
     * placeholder aunque la fila esté vacía.
     *
     * @param  array<int, mixed>  $crudo  columnas 2-206 de la fila, sin normalizar
     * @return bool true si la fila no tiene ningún dato más allá del folio
     */
    private function filaVacia(array $crudo): bool
    {
        foreach ($crudo as $n => $valor) {
            if (in_array($n, self::COLUMNAS_CON_DEFECTO_DE_PLANTILLA, true)) {
                continue;
            }

            if (! $this->esBlanco($valor)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  mixed  $valor  valor crudo de una celda
     * @return bool true si la celda equivale a "sin captura" (vacía, placeholder o numéricamente cero)
     */
    private function esBlanco(mixed $valor): bool
    {
        if ($valor === null) {
            return true;
        }

        if (is_string($valor)) {
            return in_array(mb_strtoupper(trim($valor)), self::PLACEHOLDERS, true);
        }

        if (is_numeric($valor)) {
            return (float) $valor === 0.0;
        }

        return false;
    }

    /**
     * Arma el payload plano (columnas snake_case) que entiende
     * ExpedienteBuilder a partir de las columnas crudas de una fila,
     * normalizando los catálogos cerrados y las columnas numéricas.
     *
     * @param  array<int, mixed>  $crudo  columnas 2-206 de la fila, sin normalizar
     * @param  User  $gonzalez  cuenta de Mariano Alejandro González Cuenca
     * @param  User  $villavicencio  cuenta de Evelyn Chantal Villavicencio Ramos
     * @param  User|null  $coria  cuenta del firmante oficial, si ya existe
     * @return array<string, mixed> payload plano listo para ExpedienteBuilder::importar()
     */
    private function payloadDeFila(array $crudo, User $gonzalez, User $villavicencio, ?User $coria): array
    {
        $payload = [];

        foreach (self::COLUMNAS as $n => $clave) {
            $valor = $crudo[$n] ?? null;

            $payload[$clave] = match (true) {
                $n === 11 => $this->limpiarNss($valor),
                $n === 12 => $this->limpiarCurp($valor),
                $n === 16 => $this->fechaDeCelda($valor),
                $n === 7 => $this->normalizarExacto($valor, [
                    'INGRESO / EVALUACIÓN INICIAL', 'RENOVACIÓN ANUAL', 'CONTROL PERIÓDICO / SEGUIMIENTO',
                ]),
                $n === 13 => $this->normalizarGenero($valor),
                $n === 15 => $this->normalizarExacto($valor, [
                    'PERSONAL GENERAL', 'OPERADORES', 'CAT B', 'CAT C', 'VISITA TEC',
                ]),
                $n === 25 => $this->normalizarReligion($valor),
                $n === 26 => $this->normalizarEstadoCivil($valor),
                $n === 39 => $this->normalizarEscolaridad($valor),
                $n === 82 => $this->limpiarNumeroEnRango($valor, 0, 400),
                $n === 113 => $this->normalizarLentesSeguridad($valor),
                $n === 146 => $this->normalizarGrupoSanguineo($valor),
                in_array($n, [160, 161, 162, 163, 164, 165], true) => $this->normalizarAntidoping($valor),
                in_array($n, self::COLUMNAS_AUDIOMETRIA, true) => $this->limpiarNumeroEnRango($valor, -10, 120),
                in_array($n, self::COLUMNAS_NUMERICAS_LIBRES, true) => $this->limpiarNumeroLibre($valor),
                $n === 205 => $this->limpiarGradiente($valor),
                default => $this->limpiarCelda($valor),
            };
        }

        $payload['medico_dictaminador'] = $this->resolverMedico($crudo[206] ?? null, $gonzalez, $villavicencio, $coria);

        return $payload;
    }

    /**
     * @param  mixed  $valor  valor crudo de una celda de texto
     * @return string|null el texto recortado, o null si es placeholder/vacío
     */
    private function limpiarCelda(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = is_string($valor) ? trim($valor) : trim((string) $valor);

        if ($texto === '' || in_array(mb_strtoupper($texto), self::PLACEHOLDERS, true)) {
            return null;
        }

        return $texto;
    }

    /**
     * @param  string  $texto  texto ya en mayúsculas
     * @return string el mismo texto sin acentos ni diéresis
     */
    private function sinAcentos(string $texto): string
    {
        return str_replace(['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'Ü'], ['A', 'E', 'I', 'O', 'U', 'N', 'U'], $texto);
    }

    /**
     * @param  mixed  $valor  valor crudo
     * @param  array<int, string>  $permitidos  valores oficiales exactos del catálogo
     * @return string|null el valor si coincide exactamente con el catálogo, null en otro caso
     */
    private function normalizarExacto(mixed $valor, array $permitidos): ?string
    {
        $limpio = $this->limpiarCelda($valor);

        return $limpio !== null && in_array($limpio, $permitidos, true) ? $limpio : null;
    }

    /**
     * @param  mixed  $valor  valor crudo de la columna Género
     * @return string|null 'MASCULINO'/'FEMENINO', o null si no se reconoce
     */
    private function normalizarGenero(mixed $valor): ?string
    {
        $limpio = $this->limpiarCelda($valor);

        if ($limpio === null) {
            return null;
        }

        $u = $this->sinAcentos(mb_strtoupper($limpio));

        if (str_starts_with($u, 'MASC') || $u === 'M' || $u === 'MAS') {
            return 'MASCULINO';
        }

        if (str_starts_with($u, 'FEM') || $u === 'F') {
            return 'FEMENINO';
        }

        return null;
    }

    /**
     * @param  mixed  $valor  valor crudo de la columna Estado civil
     * @return string|null el valor oficial del catálogo, o null si no se reconoce
     */
    private function normalizarEstadoCivil(mixed $valor): ?string
    {
        $limpio = $this->limpiarCelda($valor);

        if ($limpio === null) {
            return null;
        }

        $u = $this->sinAcentos(mb_strtoupper($limpio));

        return match (true) {
            str_contains($u, 'CASAD') => 'CASADO',
            str_contains($u, 'SOLTER') => 'SOLTERO',
            str_contains($u, 'UNION LIBRE') => 'UNION LIBRE',
            str_contains($u, 'DIVORCI') => 'DIVORCIADO',
            str_contains($u, 'SEPARAD') => 'SEPARADO',
            str_contains($u, 'VIUD') => 'VIUDO',
            default => null,
        };
    }

    /**
     * @param  mixed  $valor  valor crudo de la columna Religión
     * @return string|null el valor oficial del catálogo ('Otra' si tiene religión pero no coincide con ninguna), o null si venía vacío
     */
    private function normalizarReligion(mixed $valor): ?string
    {
        $limpio = $this->limpiarCelda($valor);

        if ($limpio === null) {
            return null;
        }

        $u = $this->sinAcentos(mb_strtoupper($limpio));

        return match (true) {
            str_contains($u, 'CATOLI') => 'Católica',
            str_contains($u, 'CRISTIAN') || str_contains($u, 'CRISITAN') || str_contains($u, 'CRISITIAN') => 'Cristiana',
            str_contains($u, 'NINGUNA') || str_contains($u, 'NO TIENE') || str_contains($u, 'SIN RELIGION') || str_contains($u, 'NO CUENTA') => 'Ninguna',
            str_contains($u, 'ATEO') || str_contains($u, 'ATEA') => 'Atea',
            str_contains($u, 'NEGAD') => 'Negado',
            default => 'Otra',
        };
    }

    /**
     * @param  mixed  $valor  valor crudo de la columna Escolaridad
     * @return string|null uno de los 10 valores oficiales del enum de la base de datos, o null si no se reconoce
     */
    private function normalizarEscolaridad(mixed $valor): ?string
    {
        $limpio = $this->limpiarCelda($valor);

        if ($limpio === null) {
            return null;
        }

        $u = $this->sinAcentos(mb_strtoupper($limpio));
        $incompleta = str_contains($u, 'INCOMPLET');

        return match (true) {
            str_contains($u, 'SIN ESCOLARIDAD') => 'Sin Escolaridad',
            str_contains($u, 'PRIMARIA') => $incompleta ? 'Primaria Incompleta' : 'Primaria Terminada',
            str_contains($u, 'SECUNDARIA') => $incompleta ? 'Secundaria Incompleta' : 'Secundaria Terminada',
            str_contains($u, 'BACHILLERATO') || str_contains($u, 'PREPARATORIA') => $incompleta ? 'Preparatoria Incompleta' : 'Preparatoria Terminada',
            str_contains($u, 'LICENCIATU') => $incompleta ? 'Licenciatura Incompleta' : 'Licenciatura Terminada',
            str_contains($u, 'MAESTRIA') || str_contains($u, 'POSGRADO') || str_contains($u, 'POSTGRADO') => 'Maestría / Postgrado',
            default => null,
        };
    }

    /**
     * @param  mixed  $valor  valor crudo de la columna Lentes de seguridad graduados
     * @return string|null una de las 2 frases oficiales del enum de la base de datos, o null si no se reconoce
     */
    private function normalizarLentesSeguridad(mixed $valor): ?string
    {
        $limpio = $this->limpiarCelda($valor);

        if ($limpio === null) {
            return null;
        }

        $u = $this->sinAcentos(mb_strtoupper($limpio));

        if (str_contains($u, 'NO NECESITA') || str_contains($u, 'NO REQUIERE')) {
            return 'PX NO NECESITA LENTES GRADUADOS DE SEGURIDAD';
        }

        if (str_contains($u, 'NECESITA') || str_contains($u, 'REQUIERE') || str_contains($u, 'DEBE USAR')) {
            return 'PX REQUIERE LENTES GRADUADOS DE SEGURIDAD';
        }

        return null;
    }

    /**
     * @param  mixed  $valor  valor crudo de la columna Grupo sanguíneo y Rh
     * @return string|null uno de los 8 valores oficiales del enum de la base de datos, o null si no se reconoce
     */
    private function normalizarGrupoSanguineo(mixed $valor): ?string
    {
        $limpio = $this->limpiarCelda($valor);

        if ($limpio === null) {
            return null;
        }

        $u = $this->sinAcentos(mb_strtoupper($limpio));

        $grupo = match (true) {
            str_starts_with($u, 'AB') => 'AB',
            str_starts_with($u, 'A') => 'A',
            str_starts_with($u, 'B') => 'B',
            str_starts_with($u, 'O') => 'O',
            default => null,
        };

        if ($grupo === null) {
            return null;
        }

        return $grupo.' '.(str_contains($u, 'NEG') ? 'NEGATIVO' : 'POSITIVO');
    }

    /**
     * @param  mixed  $valor  valor crudo de una columna de antidoping (ad_*)
     * @return string|null uno de los 3 valores oficiales del enum de la base de datos, o null si no se reconoce
     */
    private function normalizarAntidoping(mixed $valor): ?string
    {
        $limpio = $this->limpiarCelda($valor);

        if ($limpio === null) {
            return null;
        }

        $u = $this->sinAcentos(mb_strtoupper($limpio));

        return match (true) {
            str_contains($u, 'NO REACTIVO') => 'NO REACTIVO',
            str_contains($u, 'NO REALIZADO') => 'NO REALIZADO',
            str_contains($u, 'REACTIVO') => 'REACTIVO',
            default => null,
        };
    }

    /**
     * @param  mixed  $valor  valor crudo de la columna Gradiente
     * @return string|null el dígito 1-4 encontrado en el texto, o null si no hay ninguno
     */
    private function limpiarGradiente(mixed $valor): ?string
    {
        $limpio = $this->limpiarCelda($valor);

        if ($limpio === null) {
            return null;
        }

        return preg_match('/[1-4]/', $limpio) === 1 ? $limpio : null;
    }

    /**
     * @param  mixed  $valor  valor crudo de una columna "numeric" de la base de datos
     * @return string|null el texto tal cual si es numérico, o null si no lo es o venía vacío
     */
    private function limpiarNumeroLibre(mixed $valor): ?string
    {
        $limpio = $this->limpiarCelda($valor);

        if ($limpio === null) {
            return null;
        }

        // Postgres no entiende la coma decimal: se normaliza a punto antes de guardar.
        $normalizado = str_replace(',', '.', $limpio);

        return is_numeric($normalizado) ? $normalizado : null;
    }

    /**
     * @param  mixed  $valor  valor crudo de una columna "numeric" con rango permitido
     * @param  float  $min  mínimo permitido (exclusivo)
     * @param  float  $max  máximo permitido (inclusivo)
     * @return string|null el texto tal cual si es numérico y cae en el rango, o null en otro caso
     */
    private function limpiarNumeroEnRango(mixed $valor, float $min, float $max): ?string
    {
        $limpio = $this->limpiarCelda($valor);

        if ($limpio === null) {
            return null;
        }

        $normalizado = str_replace(',', '.', $limpio);

        if (! is_numeric($normalizado)) {
            return null;
        }

        $numero = (float) $normalizado;

        return ($numero > $min && $numero <= $max) ? $normalizado : null;
    }

    /**
     * @param  mixed  $valor  valor crudo de la columna CURP
     * @return string|null la CURP normalizada (18 caracteres), o null si no queda en 18
     */
    private function limpiarCurp(mixed $valor): ?string
    {
        $curp = $this->calculos->claveCurp($this->limpiarCelda($valor));

        return $curp !== null && strlen($curp) === 18 ? $curp : null;
    }

    /**
     * @param  mixed  $valor  valor crudo de la columna IMSS NSS
     * @return string|null el NSS normalizado (11 dígitos), o null si no queda en 11
     */
    private function limpiarNss(mixed $valor): ?string
    {
        $nss = $this->calculos->claveNss($this->limpiarCelda($valor));

        return $nss !== null && strlen($nss) === 11 ? $nss : null;
    }

    /**
     * Convierte una celda de fecha (serial de Excel, DateTime o texto en
     * DD-MM-AAAA/DD/MM/AAAA/AAAA-MM-DD) a "AAAA-MM-DD".
     *
     * @param  mixed  $valor  valor crudo de una celda de fecha
     * @return string|null la fecha en formato AAAA-MM-DD, o null si no se pudo interpretar
     */
    private function fechaDeCelda(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        if (is_numeric($valor)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $valor)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $texto = trim((string) $valor);

        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $formato) {
            $fecha = \DateTime::createFromFormat($formato, $texto);

            if ($fecha && $fecha->format($formato) === $texto) {
                return $fecha->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Resuelve el texto libre de "Médico dictaminador" a la cuenta
     * correspondiente, por coincidencia de nombre (no de cédula, que viene
     * con muchas variantes de captura).
     *
     * @param  mixed  $valor  valor crudo de la columna Médico dictaminador
     * @param  User  $gonzalez  cuenta de Mariano Alejandro González Cuenca
     * @param  User  $villavicencio  cuenta de Evelyn Chantal Villavicencio Ramos
     * @param  User|null  $coria  cuenta del firmante oficial, si ya existe
     * @return string|null el id de la cuenta reconocida, o null si no se reconoce ninguna
     */
    private function resolverMedico(mixed $valor, User $gonzalez, User $villavicencio, ?User $coria): ?string
    {
        $limpio = $this->limpiarCelda($valor);

        if ($limpio === null) {
            return null;
        }

        $u = $this->sinAcentos(mb_strtoupper($limpio));

        if (str_contains($u, 'GONZALEZ CUENCA')) {
            return $gonzalez->id;
        }

        if (str_contains($u, 'VILLAVICENCIO RAMOS')) {
            return $villavicencio->id;
        }

        if ($coria && str_contains($u, 'CORIA SALAS')) {
            return $coria->id;
        }

        return null;
    }

    /**
     * Deja un único evento resumen en la bitácora con el resultado de la
     * importación (no uno por expediente, para no inundar la bitácora).
     *
     * @param  User  $admin  cuenta a la que se le atribuye el evento
     * @param  int  $importados  total de expedientes creados
     * @param  int  $omitidosVacios  filas con folio pero sin ningún dato capturado
     * @param  int  $errores  filas que no se pudieron importar por un error
     */
    private function registrarResumenBitacora(User $admin, int $importados, int $omitidosVacios, int $errores): void
    {
        Auditoria::create([
            'usuario_id' => $admin->id,
            'usuario_nombre' => $admin->nombre_completo,
            'accion' => AccionAuditoria::ImportacionHistorica,
            'historia_id' => null,
            'anio' => (int) date('Y'),
            'folio_anual' => 0,
            'paciente' => null,
            'detalle' => "Importación histórica: {$importados} expedientes creados, {$omitidosVacios} filas vacías omitidas, {$errores} errores.",
        ]);
    }
}

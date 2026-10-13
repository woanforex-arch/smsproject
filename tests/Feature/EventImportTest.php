<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Services\EventImportService;
use App\Support\Spreadsheet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class EventImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    protected array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_the_create_page_offers_the_import_action_to_admins(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/events/create')
            ->assertOk()
            ->assertSee('Import from Excel')
            ->assertSee('Template');
    }

    public function test_the_import_action_is_hidden_from_staff(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->get('/admin/events/create')
            ->assertOk()
            ->assertDontSee('Import from Excel');
    }

    public function test_it_imports_events_from_a_csv_file(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $path = $this->writeCsv([
            ['title', 'starts_at', 'ends_at', 'venue', 'city', 'status', 'capacity'],
            ['Nairobi Tech Summit', '2026-07-18 09:00', '2026-07-18 17:00', 'KICC', 'Nairobi', 'published', '500'],
            ['Mombasa Expo', '2026-08-01 10:00', '2026-08-01 16:00', '', 'Mombasa', '', ''],
        ]);

        $result = app(EventImportService::class)->import($path);

        $this->assertSame(2, $result->created);
        $this->assertFalse($result->hasErrors());

        $this->assertDatabaseHas('events', [
            'title' => 'Nairobi Tech Summit',
            'slug' => 'nairobi-tech-summit',
            'status' => 'published',
            'city' => 'Nairobi',
            'capacity' => 500,
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('events', [
            'title' => 'Mombasa Expo',
            'status' => 'draft',
            'capacity' => null,
        ]);
    }

    public function test_it_reports_rows_it_cannot_import(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $path = $this->writeCsv([
            ['title', 'starts_at', 'ends_at', 'status'],
            ['Good Event', '2026-07-18 09:00', '2026-07-18 17:00', 'published'],
            ['', '2026-07-19 09:00', '2026-07-19 17:00', 'published'],
            ['Bad Status', '2026-07-20 09:00', '2026-07-20 17:00', 'nonsense'],
        ]);

        $result = app(EventImportService::class)->import($path);

        $this->assertSame(1, $result->created);
        $this->assertTrue($result->hasErrors());
        $this->assertArrayHasKey(3, $result->errors);
        $this->assertArrayHasKey(4, $result->errors);
    }

    public function test_it_reads_xlsx_workbooks(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $path = $this->tempFiles[] = $this->writeXlsx([
            ['title', 'starts_at', 'ends_at', 'city'],
            ['Excel Summit', '2026-09-12 09:00', '2026-09-12 18:00', 'Kisumu'],
        ]);

        $result = app(EventImportService::class)->import($path);

        $this->assertSame(1, $result->created);
        $this->assertFalse($result->hasErrors());
        $this->assertDatabaseHas('events', ['title' => 'Excel Summit', 'city' => 'Kisumu']);
    }

    public function test_it_converts_excel_date_serials(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $serial = (
            Carbon::parse('2026-07-18 09:00')->getTimestamp()
            - Carbon::create(1899, 12, 30, 0, 0, 0, config('app.timezone'))->getTimestamp()
        ) / 86400;

        $path = $this->writeCsv([
            ['title', 'starts_at', 'ends_at'],
            ['Serial Event', (string) $serial, (string) ($serial + 0.25)],
        ]);

        app(EventImportService::class)->import($path);

        $event = Event::where('title', 'Serial Event')->firstOrFail();

        $this->assertSame('2026-07-18 09:00', $event->starts_at->format('Y-m-d H:i'));
    }

    public function test_the_csv_reader_handles_semicolon_delimited_files(): void
    {
        $path = $this->writeFile("title;city\nNairobi;Kenya\n");

        $rows = Spreadsheet::rows($path);

        $this->assertSame(['title', 'city'], $rows[0]);
        $this->assertSame(['Nairobi', 'Kenya'], $rows[1]);
    }

    public function test_the_import_template_downloads_as_csv(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/exports/events/template')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_the_import_template_is_admin_only(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->get('/admin/exports/events/template')
            ->assertForbidden();
    }

    public function test_the_events_export_is_available_to_admins(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/exports/events')
            ->assertOk();
    }

    /** @param array<int, array<int, string>> $rows */
    protected function writeCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $this->writeFile($contents);
    }

    protected function writeFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import');
        file_put_contents($path, $contents);

        return $this->tempFiles[] = $path;
    }

    /** @param array<int, array<int, string>> $rows */
    protected function writeXlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';

        $shared = [];
        $index = [];
        $sharedXml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        foreach ($rows as $row) {
            foreach ($row as $value) {
                if (is_string($value) && ! is_numeric($value) && ! isset($index[$value])) {
                    $index[$value] = count($shared);
                    $shared[] = $value;
                    $sharedXml .= '<si><t>'.htmlspecialchars($value, ENT_XML1).'</t></si>';
                }
            }
        }

        $sharedXml .= '</sst>';

        $sheet = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $r => $row) {
            $sheet .= '<row r="'.($r + 1).'">';

            foreach ($row as $c => $value) {
                $ref = $this->columnLetter($c).($r + 1);

                if (is_string($value) && ! is_numeric($value)) {
                    $sheet .= '<c r="'.$ref.'" t="s"><v>'.$index[$value].'</v></c>';
                } else {
                    $sheet .= '<c r="'.$ref.'"><v>'.htmlspecialchars((string) $value, ENT_XML1).'</v></c>';
                }
            }

            $sheet .= '</row>';
        }

        $sheet .= '</sheetData></worksheet>';

        $workbook = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/></sheets></workbook>';

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/sharedStrings.xml', $sharedXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return $path;
    }

    protected function columnLetter(int $index): string
    {
        $letters = '';

        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letters = chr(($i % 26) + 65).$letters;
        }

        return $letters;
    }
}

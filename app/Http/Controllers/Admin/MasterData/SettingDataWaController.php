<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Imports\MasterData\ImportSettingDataWa;
use App\Models\scctcust;
use App\Models\ValidationMessage;
use App\Support\SchoolScope;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Maatwebsite\Excel\Validators\ValidationException;

class SettingDataWaController extends Controller
{
    public string $title = "Master Data";
    public string $mainTitle = "Update No WA";
    public string $dataTitle = "Update No WA";
    public string $cacheKey = "import_setting_data_wa";

    public function index()
    {
        $data["title"] = $this->title;
        $data["mainTitle"] = $this->mainTitle;
        $data["dataTitle"] = $this->dataTitle;
        $data["columnsUrl"] = route("admin.master-data.setting-data-wa.get-column");
        $data["datasUrl"] = route("admin.master-data.setting-data-wa.get-data");

        return view("admin.master_data.setting_data_wa.index", $data);
    }

    public function store(Request $request)
    {
        $request->validate(
            [
                "fileImport" => ["required", "mimes:xls,xlsx", "max:1024"],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes(),
        );

        $file = $request->fileImport;

        try {
            $headingsData = (new HeadingRowImport())->toArray($file);
            $requiredColumns = ["nis", "nama", "no_wa"];
            if (empty($headingsData) || !isset($headingsData[0][0])) {
                throw new Exception(
                    "Tidak dapat membaca judul kolom dari file. Pastikan file memiliki header yang sesuai.",
                );
            }

            $headings = array_map(
                fn ($h) => strtolower(trim(str_replace([" ", "-"], "_", (string) $h))),
                $headingsData[0][0],
            );
            $headings = array_map(
                fn ($h) => in_array($h, ["nowa", "no_wa"], true) ? "no_wa" : $h,
                $headings,
            );

            $missingColumns = [];
            foreach ($requiredColumns as $column) {
                if (!in_array($column, $headings, true)) {
                    $missingColumns[] = $column;
                }
            }

            if (!empty($missingColumns)) {
                $formattedMissingColumns = strtoupper(
                    str_replace("_", " ", implode(", ", $missingColumns)),
                );
                $formattedRequiredColumns = strtoupper(
                    str_replace("_", " ", implode(", ", $requiredColumns)),
                );
                throw new Exception(
                    "Kolom {$formattedMissingColumns} tidak ditemukan.<br><hr> pastikan kolom berikut ada dan terisi pada file import: {$formattedRequiredColumns}.",
                );
            }

            Cache::forget($this->cacheKey);
            Excel::import(new ImportSettingDataWa(), $file);

            $data = Cache::get($this->cacheKey) ?? [];
            $invalidCount = collect($data)->where("status", 0)->count();
            $message = "Sukses, data WA telah diimport, silahkan periksa kembali";
            if ($invalidCount > 0) {
                $message .= " ({$invalidCount} baris perlu diperbaiki, lihat kolom Keterangan)";
            }

            return response()->json(
                [
                    "message" => $message,
                    "data" => $data,
                ],
                200,
            );
        } catch (ValidationException $e) {
            $errorMessages = $e->errors();
            $errorMessage =
                $errorMessages["error"][0] ??
                "Terjadi kesalahan saat melakukan import data.";

            return response()->json(
                ["message" => $errorMessage, "error" => $errorMessages],
                422,
            );
        } catch (Exception $e) {
            $error = $e->getMessage();

            return response()->json(
                [
                    "message" => "Gagal!<br> tidak dapat melakukan {$this->mainTitle}.<hr> {$error}",
                    "error" => $error,
                ],
                422,
            );
        }
    }

    public function getColumn()
    {
        return [
            [
                "data" => null,
                "name" => "no",
                "className" => "text-center",
                "columnType" => "row",
            ],
            ["data" => "nis", "name" => "NIS", "searchable" => true, "orderable" => true],
            ["data" => "nama", "name" => "NAMA", "searchable" => true, "orderable" => true],
            ["data" => "no_wa", "name" => "No WA", "searchable" => true, "orderable" => true],
            ["data" => "unit", "name" => "Unit", "searchable" => true, "orderable" => true],
            ["data" => "kelas", "name" => "Kelas", "searchable" => true, "orderable" => true],
            ["data" => "kelompok", "name" => "Kelompok", "searchable" => true, "orderable" => true],
            ["data" => "angkatan", "name" => "Tahun Siswa", "searchable" => true, "orderable" => true],
            [
                "data" => "status",
                "name" => "Status",
                "searchable" => true,
                "orderable" => true,
                "columnType" => "importstatus",
            ],
            [
                "data" => "keterangan",
                "name" => "Keterangan",
                "searchable" => true,
                "orderable" => true,
            ],
        ];
    }

    public function getData(Request $request)
    {
        $draw = $request->get("draw");
        $start = (int) $request->get("start", 0);
        $length = (int) $request->get("length", 10);
        $cachedData = collect(Cache::get($this->cacheKey, []));
        $nisCount = $cachedData->count();
        $records = $cachedData->slice($start, $length > 0 ? $length : $nisCount)->values();

        return response()->json([
            "draw" => intval($draw),
            "recordsTotal" => $nisCount,
            "recordsFiltered" => $nisCount,
            "data" => $records,
        ]);
    }

    public function validateData()
    {
        $data = Cache::get($this->cacheKey);
        if (is_null($data) || (is_array($data) && empty($data))) {
            return response()->json(
                [
                    "message" =>
                        "Tidak ada data yang dapat diproses, silahkan upload file terlebih dahulu",
                ],
                422,
            );
        }

        $validRows = collect($data)
            ->filter(fn ($item) => ($item["status"] ?? 0) == 1 && ($item["nis"] ?? "") !== "")
            ->values();

        if ($validRows->isEmpty()) {
            return response()->json(
                [
                    "message" => "Tidak ada baris valid yang dapat disimpan. Periksa kolom Keterangan.",
                ],
                422,
            );
        }

        $connection = DB::connection("DATA_MYSQL");
        $connection->beginTransaction();
        try {
            $nisList = $validRows->pluck("nis")->unique()->values()->all();
            $existing = scctcust::query()
                ->tap(fn ($q) => SchoolScope::applyStudent($q))
                ->whereIn("NOCUST", $nisList)
                ->get()
                ->keyBy(fn ($item) => (string) $item->NOCUST);

            $updated = 0;
            foreach ($validRows as $item) {
                $existingCust = $existing->get((string) $item["nis"]);
                if (!$existingCust) {
                    continue;
                }

                $existingCust->NO_WA = $item["no_wa"];
                $existingCust->LastUpdate = date("Y-m-d H:i:s");
                $existingCust->save();
                $updated++;
            }

            if ($updated === 0) {
                $connection->rollBack();

                return response()->json(
                    ["message" => "Tidak ada nomor WA yang berhasil diperbarui"],
                    422,
                );
            }

            $connection->commit();
            Cache::forget($this->cacheKey);

            $skipped = count($data) - $updated;
            $message = "Sukses, {$updated} nomor WA siswa telah diperbarui";
            if ($skipped > 0) {
                $message .= ". {$skipped} baris dilewati karena tidak valid";
            }

            return response()->json(
                ["message" => $message],
                200,
            );
        } catch (Exception $e) {
            $connection->rollBack();

            return response()->json(
                [
                    "message" => "Gagal menyimpan data WA",
                    "error" => $e->getMessage(),
                ],
                422,
            );
        }
    }

    public function clearData()
    {
        Cache::forget($this->cacheKey);

        return response()->json(["message" => "Data dibersihkan"], 200);
    }
}

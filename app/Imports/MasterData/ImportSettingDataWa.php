<?php

namespace App\Imports\MasterData;

use App\Models\scctcust;
use App\Support\SchoolScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ImportSettingDataWa implements WithMultipleSheets, ToCollection, WithHeadingRow
{
    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    public function collection(Collection $collection): void
    {
        $cacheKey = "import_setting_data_wa";
        $parsedRows = [];

        foreach ($collection as $row) {
            if ($row->filter()->isEmpty()) {
                continue;
            }

            $rowData = $this->normalizeKeys($row->toArray());
            $nis = $this->cellString($rowData["nis"] ?? "");
            $nama = $this->cellString($rowData["nama"] ?? "");
            $noWa = $this->normalizeNoWa(
                $rowData["no_wa"] ?? $rowData["nowa"] ?? $rowData["no wa"] ?? "",
            );

            $parsedRows[] = [
                "nis" => $nis,
                "nama" => $nama,
                "no_wa" => $noWa,
                "unit" => $this->cellString($rowData["unit"] ?? ""),
                "kelas" => $this->cellString($rowData["kelas"] ?? ""),
                "kelompok" => $this->cellString($rowData["kelompok"] ?? ""),
                "angkatan" => $this->cellString(
                    $rowData["angkatan"] ?? $rowData["tahun_siswa"] ?? $rowData["thn_aka"] ?? "",
                ),
            ];
        }

        if ($parsedRows === []) {
            Cache::forget($cacheKey);
            return;
        }

        $nisList = array_values(array_filter(array_column($parsedRows, "nis")));
        $existing = $nisList !== []
            ? scctcust::query()
                ->tap(fn ($q) => SchoolScope::applyStudent($q))
                ->whereIn("NOCUST", $nisList)
                ->get(["NOCUST", "NMCUST", "CODE02", "DESC02", "DESC03", "DESC04", "NO_WA"])
                ->keyBy(fn ($item) => (string) $item->NOCUST)
            : collect();

        $processedData = [];

        foreach ($parsedRows as $rowData) {
            $rowData["status"] = 1;
            $statusKet = null;
            $nis = $rowData["nis"];
            $existingCust = $nis !== "" ? $existing->get($nis) : null;

            if ($nis === "") {
                $rowData["status"] = 0;
                $statusKet = "NIS tidak boleh kosong";
            } elseif ($rowData["no_wa"] === "") {
                $rowData["status"] = 0;
                $statusKet = "No WA tidak boleh kosong";
            } elseif (!$existingCust) {
                $rowData["status"] = 0;
                $statusKet = "NIS {$nis} tidak ditemukan";
            } else {
                if ($rowData["nama"] === "") {
                    $rowData["nama"] = (string) $existingCust->NMCUST;
                }
                if ($rowData["unit"] === "") {
                    $rowData["unit"] = (string) ($existingCust->CODE02 ?? "");
                }
                if ($rowData["kelas"] === "") {
                    $rowData["kelas"] = (string) ($existingCust->DESC02 ?? "");
                }
                if ($rowData["kelompok"] === "") {
                    $rowData["kelompok"] = (string) ($existingCust->DESC03 ?? "");
                }
                if ($rowData["angkatan"] === "") {
                    $rowData["angkatan"] = (string) ($existingCust->DESC04 ?? "");
                }

                $currentWa = trim((string) ($existingCust->NO_WA ?? ""));
                if ($currentWa === $rowData["no_wa"]) {
                    $statusKet = "No WA sama dengan data existing";
                } else {
                    $statusKet = $currentWa === ""
                        ? "No WA akan diisi"
                        : "No WA akan diupdate dari {$currentWa}";
                }
            }

            $rowData["keterangan"] = $statusKet;
            $processedData[] = $rowData;
        }

        Cache::put($cacheKey, $processedData, now()->addMinutes(60));
    }

    public function headingRow(): int
    {
        return 1;
    }

    private function normalizeKeys(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[strtolower(trim(str_replace([" ", "-"], "_", (string) $key)))] = $value;
        }

        return $normalized;
    }

    private function cellString(mixed $value): string
    {
        if ($value === null) {
            return "";
        }

        if (is_numeric($value) && !is_string($value)) {
            if ((float) $value == (int) $value) {
                return (string) (int) $value;
            }

            return trim((string) $value);
        }

        return trim((string) $value);
    }

    private function normalizeNoWa(mixed $value): string
    {
        $noWa = $this->cellString($value);
        if ($noWa === "") {
            return "";
        }

        $digits = preg_replace("/\D/", "", $noWa) ?? "";
        if ($digits === "") {
            return $noWa;
        }

        if (str_starts_with($digits, "8") && strlen($digits) >= 9 && strlen($digits) <= 13) {
            return "0" . $digits;
        }

        return $digits;
    }
}

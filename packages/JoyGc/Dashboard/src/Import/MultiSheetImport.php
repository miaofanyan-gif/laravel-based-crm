<?php

namespace JoyGc\Dashboard\Import;

use Common\Exceptions\RepositoryException;
use Common\Libs\Common;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\IOFactory;
class MultiSheetImport extends CommonImport implements WithMultipleSheets
{
    private $sheetProcessors = [];

    /**
     * 构造函数中动态获取 Excel 文件的 Sheet 数量，并为每个 Sheet 创建处理器
     */
    public function __construct($filePath)
    {
        // 加载整个 Excel 文件，获取工作表数量
        $spreadsheet = IOFactory::load($filePath);
        $sheetCount = $spreadsheet->getSheetCount();
        $cleanedExpected = array_map('trim', $this->cost_header);
        $expectedCount = count($cleanedExpected);
//        Common::printR($sheetCount);
        // 为每个 Sheet 创建一个独立的处理器实例（传入文件名）
        for ($i = 0; $i < $sheetCount; $i++) {
            $sheet = $spreadsheet->getSheet($i);
            $sheetTitle = $sheet->getTitle();

            // 获取第一行的数据（从 A 列到最大列）
            $highestColumn = $sheet->getHighestColumn();
            $rowData = $sheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE);

            if (empty($rowData) || empty($rowData[0])) {
                $errors[] = "Sheet {$i}（{$sheetTitle}）为空，无法获取表头";
                continue;
            }

            if(count($rowData) != count($this->cost_header)){
                $it = 0;
                foreach ($this->cost_header as $kk => $value) {//循环定义好的数据，防止表格右边有多余的空格
                    if(!empty($row[$it]) && $row[$it] != $value){
                        throw new RepositoryException('您导入消耗的表头（' . $row[$it] . '）数据中不存在[' . implode(',', $this->cost_header) . ']，请核对后在上传', 5001);
                    }
                    $it ++;
                }
                unset($it,$kk);
            }

            $firstRow = $rowData[0];
            // 去除每个单元格的前后空格
            $firstRow = array_map('trim', $firstRow);
            Common::printR($firstRow);
//
//            // 检查列数是否足够
//            if (count($firstRow) < $expectedCount) {
//                $errors[] = "Sheet {$i}（{$sheetTitle}）表头列数不足：预期 {$expectedCount} 列，实际 " . count($firstRow) . " 列";
//                continue;
//            }
//
//            // 只比较前 $expectedCount 列
//            $actualHeaders = array_slice($firstRow, 0, $expectedCount);
//
//            // 逐一对比（顺序敏感）
//            for ($j = 0; $j < $expectedCount; $j++) {
//                if ($actualHeaders[$j] !== $cleanedExpected[$j]) {
//                    $errors[] = "Sheet {$i}（{$sheetTitle}）第 " . ($j+1) . " 列表头不正确：预期 “{$cleanedExpected[$j]}”，实际 “{$actualHeaders[$j]}”";
//                    break;
//                }
//            }
            $this->sheetProcessors[] = new BatchDataImport("sheet【{$sheetTitle}】",$this->cost_header);
        }

//        // 释放内存
//        $spreadsheet->disconnectWorksheets();
//        unset($spreadsheet);
    }

    /**
     * 返回所有 Sheet 对应的处理器（索引从 0 开始）
     */
    public function sheets(): array
    {
        return $this->sheetProcessors;
    }

    /**
     * 获取所有处理器汇总的结果
     */
    public function getAllResults(): array
    {
        $allResults = [];
        foreach ($this->sheetProcessors as $processor) {
            $allResults = array_merge_recursive($allResults, $processor->getResult());
        }
        return $allResults;
    }

    /**
     * 验证 Excel 文件中所有 Sheet 的表头是否正确（不使用 toArray）
     *
     * @param UploadedFile $file
     * @return array ['status' => bool, 'message' => string, 'details' => array]
     */
    protected function validateAllSheetsHeadersWithSpreadsheet($file)
    {
        // 加载文件
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheetCount = $spreadsheet->getSheetCount();
        $cleanedExpected = array_map('trim', $this->cost_header);
        $expectedCount = count($cleanedExpected);
        $errors = [];

        for ($i = 0; $i < $sheetCount; $i++) {
            $sheet = $spreadsheet->getSheet($i);
            $sheetTitle = $sheet->getTitle();

            // 获取第一行的数据（从 A 列到最大列）
            $highestColumn = $sheet->getHighestColumn();
            $rowData = $sheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE);

            if (empty($rowData) || empty($rowData[0])) {
                $errors[] = "Sheet {$i}（{$sheetTitle}）为空，无法获取表头";
                continue;
            }

            $firstRow = $rowData[0];
            // 去除每个单元格的前后空格
            $firstRow = array_map('trim', $firstRow);

            // 检查列数是否足够
            if (count($firstRow) < $expectedCount) {
                $errors[] = "Sheet {$i}（{$sheetTitle}）表头列数不足：预期 {$expectedCount} 列，实际 " . count($firstRow) . " 列";
                continue;
            }

            // 只比较前 $expectedCount 列
            $actualHeaders = array_slice($firstRow, 0, $expectedCount);

            // 逐一对比（顺序敏感）
            for ($j = 0; $j < $expectedCount; $j++) {
                if ($actualHeaders[$j] !== $cleanedExpected[$j]) {
                    $errors[] = "Sheet {$i}（{$sheetTitle}）第 " . ($j+1) . " 列表头不正确：预期 “{$cleanedExpected[$j]}”，实际 “{$actualHeaders[$j]}”";
                    break;
                }
            }
        }

        // 释放内存
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (!empty($errors)) {
            return [
                'status' => false,
                'message' => '表头验证失败：' . implode('；', $errors),
                'details' => $errors
            ];
        }

        return [
            'status' => true,
            'message' => '所有 Sheet 表头验证通过',
            'details' => []
        ];
    }
}

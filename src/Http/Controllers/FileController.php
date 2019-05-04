<?php

namespace Belca\File\Http\Controllers;

use Belca\File\Contracts\FileRepository;
use Belca\File\Contracts\FileController as FileControllerInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Belca\File\Http\Requests\FileRequest;
use Belca\Support\Config;
use Belca\FileHandler\FileHandler;
use Belca\GeName\GeName;

/**
 * Базовая загрузка файлов и управление ими.
 */
class FileController implements FileControllerInterface
{
    /**
     * Массив отношений между названиями представлений (индексов) и их индексом
     * в конфигурации (путь к шаблону в конфигурации).
     *
     * @var array
     */
    protected $viewConfig = [
        'index' => 'file.index_view',
        'create' => 'file.create_view',
        'show' => 'file.show_view',
        'edit' => 'file.edit_view',
        'delete' => 'file.delete_view',
    ];

    /**
     * Соотношение имен представлений и их шаблонами.
     *
     * @var array
     */
    protected $views = [];

    /**
     * Массив отношений между названиями редиректов (названия методов вызывающих
     * ридеректы) и их индексом в конфигурации (имя маршрута в конфигурации).
     *
     * @var array
     */
    protected $redirectConfig = [
        'store' => 'file.store_redirect',
        'update' => 'file.update_redirect',
        'destory' => 'file.destory_redirect',
    ];

    /**
     * Соотношение имен методов и их редиректами.
     *
     * @var array
     */
    protected $redirects = [];

    public function __construct(FileRepository $files)
    {
        $this->files = $files;

        $this->views = Config::getConfigArrayByConfigKeys($this->viewConfig);

        $this->redirects = Config::getConfigArrayByConfigKeys($this->redirectConfig);
    }

    /**
     * Отображает страницу с файлами и форму для фильтрации результата
     *
     * @param  mixed $request Условия фильтрации
     * @return View
     */
    public function index(Request $request)
    {
        $files = $this->files->filter($request->input())->paginate();

        return view($this->views['index'] ?? 'belca-file::index', compact('files'));
    }

    /**
     * Отображает форму загрузки нового файла.
     *
     * @return View
     */
    public function create()
    {
        return view($this->views['create'] ?? 'belca-file::create');
    }

    /**
     * Сохраняет файл на сервере.
     *
     * Вызывает обработку файла и сохраняет информацию
     * в БД. Если при обработке файла были получены модификации или изменена
     * информация об оригинальном файле, то эти данные также вносятся в БД.
     * После сохранения информации вызывается перенаправление на другую страницу
     * и передается статус о действии.
     *
     * @param  FileRequest   $request    Данные формы и загружаемый файл
     * @return Illuminate\Http\RedirectResponse
     */
    public function store(FileRequest $request)
    {
        $fileinfo = [
            'filename' => $request->file('file')->getClientOriginalName(),
            'title' => pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME),
            'mime' => $request->file('file')->getMimeType(),
            'extension' => $request->file('file')->clientExtension() ?? pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_EXTENSION),
        ];

        // TODO: Директорию (диск) для сохранения можно будет выбирать при загрузке файла из конфигурации filesystems
        $disk = 'public';
        $directory = Storage::disk($disk)->getAdapter()->getPathPrefix();

        // Генерируем новое имя файла
        $gename = new GeName();
        // TODO в конфигурацию, причем для каждого драйвера можно свой тип
        // или вынести отсюда и использовать трейт или метод с трейтом
        $gename->setPattern('{mime<value:group>}/{date<format:Y-m-d>}/{string<length:25>}.{extension}');
        $gename->setInitialData([
            'mime' => $fileinfo['mime'],
            'extension' => $fileinfo['extension'],
            'filename' => $fileinfo['filename'],
        ]);
        $gename->setDirectory($directory);
        $filename = $gename->generateName();

        $fileHandler = new FileHandler();
        $fileHandler->setHandlers(config('filehandler.handlers'));

        // TODO должны быть заданы настройки обработчиков: список названий и классов
        // Настройки задаются из файлов конфигурации PHP, но должна быть возможность
        // замены этих настроек или слияния другими параметрами, например,
        // заданными сторонними конфигураторами.

        // Настраиваем и запускаем обработчик оригинального файла
        // Конфигурация хранится в настройках config/filehandler.php
        $fileHandler->setOriginalFileHandlingRules(config('filehandler.original_file_handling_rules'));
        $fileHandler->setOriginalFileHandlingScript(config('filehandler.original_file_handling_scripts'));
        $fileHandler->setExecutableScriptHandlingOriginalFile('user-device');

        $fileHandler->setOriginalFile($request->file('file')->getPathName(), $fileinfo);
        $fileHandler->setDirectory($directory);

        // Если файл не сохранен - вернет false и вызовем переадресацию
        // с отображением ошибки на экране.
        if (! $fileHandler->save($filename)) {
            return redirect()->route($this->redirects['store'], ['file' => $fileinfo])->with('status', 'notSaved');
        }

        // Запускаем обработка оригинального файла по правилам указанным выше
        $fileHandler->handleOriginalFile($request->input('original_file_handler_parameters'));

        // Пример входных данных с формы
        $ofhp = [
            'handlers' => [
                'finfo' => [
                    'color'
                ]
            ]
        ];

        // Получаем информацию об оригинальном файле и сохраняем ее в БД.
        $fileHandler->setBasicPropertyNames();
        $basicFileinfo = $fileHandler->getBasicFileProperties();
        $additionalFileinfo = $fileHandler->getAdditionalFileProperties();

        // Информация для таблицы файлов
        $systemInfo = [
            'disk' => $disk,
            'author_id' => 1,
        ];

        dd($basicFileinfo);
        $file = $this->files->createWithGuardedFields(array_merge($request->input(), $systemInfo, $basicFileinfo));

        // Запускаем обработку файла.
        // Конфигурация задается из файла config/filehandler.php и настройки
        // задаются вручную. Каждый ключ массива соответствует пакету обработки.
        // Настройки обработки файлов переопределяются или задаются с формы
        // в виде массива с именем handler_parameters.<package>.*, где
        // package - название пакета, принимаемое и обрабатываемое
        // в методе handle.
        $fileHandler->setHandlerConfig(config('filehandler')); // TODO это общая конфигурация
        $fileHandler->setExecutableScriptHandlingFile('user-device');
        $fileHandler->handle($request->input('handler_parameters'));
        $files = $fileHandler->getAllInfo();

        if (isset($files) && is_array($files)) {
            $this->files->createModifications($file->id, $files);
        }

        return redirect()->route($this->redirects['store'], ['file' => $file->toArray()])->with('status', 'saved');
    }

    /**
     * Отображает страницу с файлом для загрузки или отображения, также
     * содержит сведения о модификациях.
     *
     * @param  integer $id ID файла
     * @return View
     */
    public function show($id)
    {
        $file = $this->files->findWithModifications($id);

        return view($this->views['show'] ?? 'belca-file::show', compact('file'));
    }

    /**
     * Отображает форму редактирования данных файла.
     *
     * @param  integer $id ID файла
     * @return View
     */
    public function edit($id)
    {
        $file = $this->files->find($id);

        return view($this->views['edit'] ?? 'belca-file::edit', compact('file'));
    }

    /**
     * Обновляет информацию о файле.
     * После обновления информации выполняется перенаправление на другую страницу
     * с возвратом статуса о действии.
     *
     * @param  FileRequest $request Данные формы
     * @param  integer      $id     ID файла
     * @return Illuminate\Http\RedirectResponse
     */
    public function update(FileRequest $request, $id)
    {
        $file = $this->files->update($request, $id)->toArray();

        return redirect()->route($this->redirects['update'], compact('file'))->with('status', 'updated');
    }

    /**
     * Отображает страницу с данными о файле: связи, зависимости и т.п.
     * Содержит форму удаления файла.
     *
     * @param  integer $id ID файла
     * @return View
     */
    public function delete($id)
    {
        $file = $this->files->findWithModifications($id);

        return view($this->views['delete'] ?? 'belca-file::delete', compact('file'));
    }

    /**
     * Удаляет файл, удаляет модификации, вызывает дополнительную обработку
     * данных, например, удаление связей и удаляет данные из БД.
     * После завершения удаления файлов перенаправляет на другую страницу
     * и возвращает статус удаления.
     *
     * @param  FileRequest $request Данные формы
     * @param  integer $id          ID файла
     * @return Illuminate\Http\RedirectResponse
     */
    public function destroy(FileRequest $request, $id)
    {
        // Изменение состояний связанных объектов - отдельный класс
        // Удаление файлов - отдельный класс
        // Можно удаление пустых папок - в предыдущем

        $file = $this->files->find($id)->toArray();

        $this->files->delete($id); // TODO удаление связанных данных

        return redirect()->route($this->redirects['destroy'], compact('file'))->with('status', 'deleted');
    }
}

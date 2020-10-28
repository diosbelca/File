<?php

use Dios\System\File\FileSource;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('files', function (Blueprint $table) {
          $table->increments('id');
          $table->timestamps();

          /**
           * Источник появления файла.
           *
           * Например, загружен с ПК (USER_DEVICE), сгенерирован системой (SYSTEM),
           * загружен с интернета (INTERNET), скопирован на сервере (COPY) и т.д.
           */
          $table->string('source')->default(FileSource::DEFAULT)->index();

          /**
           * Родительский файл.
           *
           * Файл, от которого был создан или модифицирован. Используется
           * для отображения группы файлов (например, разных размеров) или
           * удаления всех производных файлов.
           */
          $table->unsignedInteger('parent_id')->default(0)->index();

          /**
           * Относительный путь к файлу с именем файла.
           *
           * Используется для нахождения файла на диске.
           * Например, /home/user/files/example-name-123.jpg
           */
          $table->text('path');

          /**
           * Размер файла в байтах.
           *
           * Используется для отображения размеров файлов и подсчета занимаемого
           * места на сервере.
           */
          $table->unsignedInteger('size')->default(0);

          /**
           * Кодовое имя носителя.
           *
           * Соответствует значению конфигурационного файла
           * config/filesystems.php в Laravel
           */
          $table->string('disk')->default('public');

          /**
           * MimeType.
           *
           * Используется при загрузке файла (выдает формат скачиваемого файла)
           * и может использоваться при группировке файлов.
           */
          $table->string('mime')->default('application/octet-stream')->index();

          /**
           * Драйвер обработки файла.
           *
           * Отвечает за методы обработки файлов. Название драйвера может
           * совпадать с полем mime и/или с полем extension (зависит
           * от реализации FileHandler).
           *
           * Если у файла нету обработчика, то ему присваивается значение other.
           * Такие файлы обычно сохраняются в неизменном виде.
           */
          $table->string('driver')->default('other')->index();

          /**
           * Метод обработки файла (обработчик файла).
           *
           * Используется совместно с полем driver. Метод обработки файла
           * отражает, каким способом был создан файл или к какому типу
           * модификаций он относится.
           *
           * Например, resize - масштабирование файла, thumbnail - миниатюра
           * файла, preview - предпросмотр файла, signature - файл с подписью и т.д.
           *
           * Для файлов, к которым не была применена обработка (сохранены "как есть")
           * имеют значение обработчика original.
           */
          $table->string('handler')->default('original')->index();

          /**
           * Режим обработчика (обработки) файла.
           *
           * Используется совместно с полями handler и driver.
           * Режим обработчика файла отображает какой результат был достигнут
           * при помощи обработки или какая модификация была получена при помощи
           * обработки файла.
           *
           * Например, для метода обработки (handler) resize это могут быть
           * следующие виды: tiny - крошечный, small - маленький, normal - большой.
           * В БД и конкретно к драйверу нет никакой привязки названий. Все
           * параметры указываются в настройках системы.
           */
          $table->string('handler_mode')->default('default')->index();

          /**
           * Заголовок файла (документа).
           *
           * Человеко-понятное имя файла. Может использоваться при скачивании
           * файла, выдавая данное имя и расширение файла.
           */
          $table->string('title');

          /**
           * Расширение файла.
           *
           * Может использоваться при скачивании файла или для группировки файлов.
           */
          $table->string('extension')->nullable();

          /**
           * Описание файла.
           *
           * Может использоваться при выводе изображений.
           */
          $table->text('description')->nullable();

          /**
           * Создатель (загрузчик) файла.
           */
          $table->unsignedInteger('author_id')->index();

          /**
           * Активность файла.
           *
           * Только активные файлы могут быть получены для пользователя.
           */
          $table->boolean('active')->default(true)->index();

          /**
           * Достук к файлу.
           *
           * Доступные файлы можно скачивать по прямой ссылке из параметра slug.
           */
          $table->boolean('published')->default(false)->index();

          /**
           * ЧПУ для прямых ссылок для скачивания.
           *
           * Возможно только одна активная ссылка (работает совместно с published).
           */
          $table->string('slug')->nullable()->index();

          /**
           * Параметр файла.
           *
           * Параметр имеет строковый вид и для каждой группы отличается.
           * Например, если это изображение, то для него могут указываться
           * размеры изображений, все мета-данные файла (изображения),
           * данные для CSS, типа srcset и размера ширины изображения.
           * Если это аудио-файл, то для него может указывать длина зависи,
           * частота звука, автор и т.п.
           * Соответственно для видео, это будет длина видео, качество видео,
           * язык видео и т.п.
           *
           * Все данные имеют необязательный характер и могут свободно добавляться
           * и удаляться, а при их использовании обязательно должна присутствовать
           * проверка и значение по умолчанию, если отсутствие значения вызовет
           * ошибку или некорректное отображение данных.
           */
          $table->text('options')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('files');
    }
}

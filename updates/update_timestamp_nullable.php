<?php
namespace WixCore\Blog\Updates;

use October\Rain\Database\Updates\Migration;
use DbDongle;

class UpdateTimestampsNullable extends Migration
{
    public function up()
    {
        DbDongle::disableStrictMode();

        DbDongle::convertTimestamps('wixcore_blog_posts');
        DbDongle::convertTimestamps('wixcore_blog_categories');
    }

    public function down()
    {
        // ...
    }
}

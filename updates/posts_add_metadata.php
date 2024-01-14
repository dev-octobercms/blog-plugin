<?php
namespace WixCore\Blog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;
use WixCore\Blog\Models\Category as CategoryModel;

class PostsAddMetadata extends Migration
{

    public function up()
    {
        if (Schema::hasColumn('wixcore_blog_posts', 'metadata')) {
            return;
        }

        Schema::table('wixcore_blog_posts', function ($table) {
            $table->mediumText('metadata')->nullable();
        });
    }

    public function down()
    {
        if (Schema::hasColumn('wixcore_blog_posts', 'metadata')) {
            Schema::table('wixcore_blog_posts', function ($table) {
                $table->dropColumn('metadata');
            });
        }
    }

}

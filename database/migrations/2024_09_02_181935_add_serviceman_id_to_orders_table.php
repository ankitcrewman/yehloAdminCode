use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddServicemanIdToOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('serviceman_id')->nullable()->after('delivery_man_id');

            // Adding the foreign key constraint
            $table->foreign('serviceman_id')
                  ->references('id')
                  ->on('servicemen')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['serviceman_id']);
            $table->dropColumn('serviceman_id');
        });
    }
}

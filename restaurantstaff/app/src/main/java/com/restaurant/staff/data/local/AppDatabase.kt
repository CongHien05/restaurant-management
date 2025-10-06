package com.restaurant.staff.data.local

import android.content.Context
import androidx.room.*
import androidx.sqlite.db.SupportSQLiteDatabase
import com.restaurant.staff.data.model.*


@Database(
    entities = [
        User::class,
        Area::class,
        Table::class,
        Category::class,
        MenuItem::class,
        Order::class,
        OrderItem::class
    ],
    version = 7,
    exportSchema = false
)
@TypeConverters(Converters::class)
abstract class AppDatabase : RoomDatabase() {

    abstract fun userDao(): UserDao
    abstract fun tableDao(): TableDao
    abstract fun areaDao(): AreaDao
    abstract fun menuDao(): MenuDao
    abstract fun orderDao(): OrderDao

    companion object {
        @Volatile
        private var INSTANCE: AppDatabase? = null

        // All migrations removed - using fallbackToDestructiveMigration for clean schema

        fun getDatabase(context: Context): AppDatabase {
            return INSTANCE ?: synchronized(this) {
                val instance = Room.databaseBuilder(
                    context.applicationContext,
                    AppDatabase::class.java,
                    "restaurant_staff_database"
                )
                    // No migrations needed - using fallbackToDestructiveMigration
                    .fallbackToDestructiveMigration() // This will recreate database if schema doesn't match
                    .build()
                INSTANCE = instance
                instance
            }
        }
    }
}

class Converters {
    @TypeConverter
    fun fromOrderItemList(value: List<OrderItem>?): String? {
        return value?.let { com.google.gson.Gson().toJson(it) }
    }

    @TypeConverter
    fun toOrderItemList(value: String?): List<OrderItem>? {
        return value?.let {
            com.google.gson.Gson().fromJson(it, Array<OrderItem>::class.java).toList()
        }
    }
}

package com.restaurant.staff.utils

import android.content.Context
import android.util.Log
import com.restaurant.staff.data.local.AppDatabase
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

object DatabaseTestHelper {

    /**
     * Test database schema and migration
     */
    fun testDatabaseSchema(context: Context) {
        try {
            val database = AppDatabase.getDatabase(context)
            val db = database.openHelper.readableDatabase

            // Check if tables table exists
            val cursor = db.query("SELECT name FROM sqlite_master WHERE type='table' AND name='tables'")
            val tableExists = cursor.count > 0
            cursor.close()

            Log.d("DatabaseTest", "Tables table exists: $tableExists")

            if (tableExists) {
                // Check table schema
                val schemaCursor = db.query("PRAGMA table_info(tables)")
                val columns = mutableListOf<String>()
                while (schemaCursor.moveToNext()) {
                    val columnName = schemaCursor.getString(1)
                    val columnType = schemaCursor.getString(2)
                    columns.add("$columnName ($columnType)")
                    Log.d("DatabaseTest", "Column: $columnName ($columnType)")
                }
                schemaCursor.close()

                // Check if new columns exist
                val hasPendingAmount = columns.any { it.contains("pending_amount") }
                val hasActiveOrders = columns.any { it.contains("active_orders") }

                Log.d("DatabaseTest", "Has pending_amount: $hasPendingAmount")
                Log.d("DatabaseTest", "Has active_orders: $hasActiveOrders")
            }

        } catch (e: Exception) {
            Log.e("DatabaseTest", "Error testing database schema: ${e.message}")
        }
    }

    /**
     * Clear database completely
     */
    fun clearDatabaseCompletely(context: Context) {
        try {
            val database = AppDatabase.getDatabase(context)
            database.clearAllTables()
            Log.d("DatabaseTest", "Database cleared completely")
        } catch (e: Exception) {
            Log.e("DatabaseTest", "Error clearing database: ${e.message}")
        }
    }

    /**
     * Test table operations
     */
    fun testTableOperations(context: Context) {
        CoroutineScope(Dispatchers.IO).launch {
            try {
                val database = AppDatabase.getDatabase(context)
                val tableDao = database.tableDao()

                // Test getting all tables
                val tables = tableDao.getAllTables()
                Log.d("DatabaseTest", "Found ${tables.size} tables in database")

                // Test getting tables by area
                if (tables.isNotEmpty()) {
                    val firstTable = tables.first()
                    val tablesByArea = tableDao.getTablesByArea(firstTable.areaId)
                    Log.d("DatabaseTest", "Found ${tablesByArea.size} tables in area ${firstTable.areaId}")
                }

            } catch (e: Exception) {
                Log.e("DatabaseTest", "Error testing table operations: ${e.message}")
            }
        }
    }
}

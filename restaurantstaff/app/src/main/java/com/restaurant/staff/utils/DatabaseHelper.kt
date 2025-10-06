package com.restaurant.staff.utils

import android.content.Context
import android.util.Log
import com.restaurant.staff.data.local.AppDatabase

object DatabaseHelper {

    /**
     * Clear all data from database (use for debugging)
     */
    fun clearDatabase(context: Context) {
        try {
            val database = AppDatabase.getDatabase(context)
            database.clearAllTables()
            Log.d("DatabaseHelper", "Database cleared successfully")
        } catch (e: Exception) {
            Log.e("DatabaseHelper", "Error clearing database: ${e.message}")
        }
    }

    /**
     * Check database integrity
     */
    fun checkDatabaseIntegrity(context: Context): Boolean {
        return try {
            val database = AppDatabase.getDatabase(context)
            database.openHelper.readableDatabase
            Log.d("DatabaseHelper", "Database integrity check passed")
            true
        } catch (e: Exception) {
            Log.e("DatabaseHelper", "Database integrity check failed: ${e.message}")
            false
        }
    }
}

package com.restaurant.staff.data.local

import androidx.room.*
import com.restaurant.staff.data.model.Area
import com.restaurant.staff.data.model.Table
import kotlinx.coroutines.flow.Flow

@Dao
interface AreaDao {

    @Query("SELECT * FROM areas ORDER BY name ASC")
    fun getAllAreasFlow(): Flow<List<Area>>

    @Query("SELECT * FROM areas ORDER BY name ASC")
    suspend fun getAllAreas(): List<Area>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAreas(areas: List<Area>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertArea(area: Area)

    @Update
    suspend fun updateArea(area: Area)

    @Delete
    suspend fun deleteArea(area: Area)

    @Query("DELETE FROM areas")
    suspend fun deleteAllAreas()
}

@Dao
interface TableDao {

    @Query("SELECT * FROM tables WHERE area_id = :areaId ORDER BY name ASC")
    fun getTablesByAreaFlow(areaId: Int): Flow<List<Table>>

    @Query("SELECT * FROM tables WHERE area_id = :areaId ORDER BY name ASC")
    suspend fun getTablesByArea(areaId: Int): List<Table>

    @Query("SELECT * FROM tables ORDER BY area_id ASC, name ASC")
    fun getAllTablesFlow(): Flow<List<Table>>

    @Query("SELECT * FROM tables ORDER BY area_id ASC, name ASC")
    suspend fun getAllTables(): List<Table>

    @Query("SELECT * FROM tables WHERE id = :tableId")
    suspend fun getTableById(tableId: Int): Table?

    @Query("SELECT * FROM tables WHERE status = :status")
    suspend fun getTablesByStatus(status: String): List<Table>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertTables(tables: List<Table>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertTable(table: Table)

    @Update
    suspend fun updateTable(table: Table)

    @Delete
    suspend fun deleteTable(table: Table)

    @Query("DELETE FROM tables")
    suspend fun deleteAllTables()

    @Query("UPDATE tables SET status = :status WHERE id = :tableId")
    suspend fun updateTableStatus(tableId: Int, status: String)

    @Query("UPDATE tables SET status = 'occupied' WHERE id = :tableId")
    suspend fun updateTableOrderInfo(tableId: Int)
}

package com.restaurant.staff.data.repository

import android.util.Log
import com.restaurant.staff.data.local.AreaDao
import com.restaurant.staff.data.local.TableDao
import com.restaurant.staff.data.model.*
import com.restaurant.staff.data.remote.TableApiService
import com.restaurant.staff.utils.NetworkUtils
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow
import kotlinx.coroutines.flow.map

class TableRepository(
    private val tableApiService: TableApiService,
    private val tableDao: TableDao,
    private val areaDao: AreaDao
) {

    fun getAllAreasFlow(): Flow<List<Area>> {
        return areaDao.getAllAreasFlow()
    }

    fun getTablesByAreaFlow(areaId: Int): Flow<List<Table>> {
        return tableDao.getTablesByAreaFlow(areaId)
    }

    fun getAllTablesFlow(): Flow<List<Table>> {
        return tableDao.getAllTablesFlow()
    }

    // Simple methods for ViewModels
    suspend fun getAreas(): List<Area> {
        return try {
            Log.d("TableRepository", "Calling getAreas API...")
            val result = NetworkUtils.safeApiCall { tableApiService.getAreas() }
            Log.d("TableRepository", "getAreas result: $result")
            when (result) {
                is NetworkResult.Success -> {
                    Log.d("TableRepository", "Areas loaded from API: ${result.data.size}")
                    result.data
                }
                else -> {
                    Log.d("TableRepository", "Using cached areas")
                    areaDao.getAllAreas() // fallback to cache
                }
            }
        } catch (e: Exception) {
            Log.e("TableRepository", "Error getting areas", e)
            areaDao.getAllAreas() // fallback to cache
        }
    }

    suspend fun getTables(page: Int = 1, limit: Int = 20, areaId: Int? = null): List<Table> {
        return try {
            Log.d("TableRepository", "Calling getTables API for areaId: $areaId")
            val result = NetworkUtils.safeApiCall { tableApiService.getAllTables() }
            Log.d("TableRepository", "getTables result: $result")
            when (result) {
                is NetworkResult.Success -> {
                    val tableResponse = result.data
                    var tables = tableResponse.tables
                    Log.d("TableRepository", "Tables loaded from API: ${tables.size}")

                    // Debug: Log table details
                    tables.forEach { table ->
                        Log.d("TableRepository", "API Table ${table.tableNumber}: pendingAmount=${table.pendingAmount}, totalAmount=${table.totalAmount}, currentOrderId=${table.currentOrderId}, orderStatus=${table.orderStatus}")
                    }

                    // Filter by area if specified
                    if (areaId != null) {
                        tables = tables.filter { it.areaId == areaId }
                        Log.d("TableRepository", "Filtered tables for area $areaId: ${tables.size}")
                    }
                    // Simple pagination
                    val start = (page - 1) * limit
                    val end = minOf(start + limit, tables.size)
                    val finalTables = if (start < tables.size) tables.subList(start, end) else emptyList()
                    Log.d("TableRepository", "Final tables after pagination: ${finalTables.size}")
                    finalTables
                }
                else -> {
                    Log.d("TableRepository", "Using cached tables")
                    if (areaId != null) {
                        tableDao.getTablesByArea(areaId)
                    } else {
                        tableDao.getAllTables()
                    }
                }
            }
        } catch (e: Exception) {
            Log.e("TableRepository", "Error getting tables", e)
            if (areaId != null) {
                tableDao.getTablesByArea(areaId)
            } else {
                tableDao.getAllTables()
            }
        }
    }

    suspend fun updateTableStatus(tableId: Int, status: String) {
        try {
            tableDao.updateTableStatus(tableId, status)
            // Also update via API
            NetworkUtils.safeApiCall {
                tableApiService.updateTableStatus(tableId, UpdateTableStatusRequest(status))
            }
        } catch (e: Exception) {
            throw e
        }
    }

    suspend fun refreshAreas(): Flow<Resource<List<Area>>> = flow {
        emit(Resource.Loading())

        try {
            val result = NetworkUtils.safeApiCall { tableApiService.getAreas() }

            when (result) {
                is NetworkResult.Success -> {
                    val areas = result.data
                    areaDao.insertAreas(areas)
                    emit(Resource.Success(areas))
                }
                is NetworkResult.Error -> {
                    // Return cached data if available
                    val cachedAreas = areaDao.getAllAreas()
                    if (cachedAreas.isNotEmpty()) {
                        emit(Resource.Success(cachedAreas))
                    } else {
                        emit(Resource.Error(result.message))
                    }
                }
                is NetworkResult.Loading -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            // Return cached data if available
            val cachedAreas = areaDao.getAllAreas()
            if (cachedAreas.isNotEmpty()) {
                emit(Resource.Success(cachedAreas))
            } else {
                emit(Resource.Error(e.message ?: "Failed to refresh areas"))
            }
        }
    }

    suspend fun refreshTablesByArea(areaId: Int): Flow<Resource<TableWithArea>> = flow {
        emit(Resource.Loading())

        try {
            val result = NetworkUtils.safeApiCall { tableApiService.getTablesByArea(areaId) }

            when (result) {
                is NetworkResult.Success -> {
                    val tableWithArea = result.data

                    // Cache the area and tables
                    areaDao.insertArea(tableWithArea.area)
                    tableDao.insertTables(tableWithArea.tables)

                    emit(Resource.Success(tableWithArea))
                }
                is NetworkResult.Error -> {
                    // Return cached data if available
                    val cachedArea = areaDao.getAllAreas().find { it.id == areaId }
                    val cachedTables = tableDao.getTablesByArea(areaId)

                    if (cachedArea != null) {
                        val cachedResult = TableWithArea(cachedArea, cachedTables)
                        emit(Resource.Success(cachedResult))
                    } else {
                        emit(Resource.Error(result.message))
                    }
                }
                is NetworkResult.Loading -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            // Return cached data if available
            val cachedArea = areaDao.getAllAreas().find { it.id == areaId }
            val cachedTables = tableDao.getTablesByArea(areaId)

            if (cachedArea != null) {
                val cachedResult = TableWithArea(cachedArea, cachedTables)
                emit(Resource.Success(cachedResult))
            } else {
                emit(Resource.Error(e.message ?: "Failed to refresh tables"))
            }
        }
    }

    suspend fun refreshAllTables(): Flow<Resource<List<Table>>> = flow {
        emit(Resource.Loading())

        try {
            val result = NetworkUtils.safeApiCall { tableApiService.getAllTables() }

            when (result) {
                is NetworkResult.Success -> {
                    val tableResponse = result.data
                    val tables = tableResponse.tables
                    tableDao.insertTables(tables)
                    emit(Resource.Success(tables))
                }
                is NetworkResult.Error -> {
                    // Return cached data if available
                    val cachedTables = tableDao.getAllTables()
                    if (cachedTables.isNotEmpty()) {
                        emit(Resource.Success(cachedTables))
                    } else {
                        emit(Resource.Error(result.message))
                    }
                }
                is NetworkResult.Loading -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            // Return cached data if available
            val cachedTables = tableDao.getAllTables()
            if (cachedTables.isNotEmpty()) {
                emit(Resource.Success(cachedTables))
            } else {
                emit(Resource.Error(e.message ?: "Failed to refresh tables"))
            }
        }
    }

    suspend fun updateTableStatusWithFlow(tableId: Int, status: String): Flow<Resource<Table>> = flow {
        emit(Resource.Loading())

        try {
            val request = UpdateTableStatusRequest(status)
            val result = NetworkUtils.safeApiCall {
                tableApiService.updateTableStatus(tableId, request)
            }

            when (result) {
                is NetworkResult.Success -> {
                    val updatedTable = result.data
                    tableDao.insertTable(updatedTable)
                    emit(Resource.Success(updatedTable))
                }
                is NetworkResult.Error -> {
                    emit(Resource.Error(result.message))
                }
                is NetworkResult.Loading -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            emit(Resource.Error(e.message ?: "Failed to update table status"))
        }
    }

    suspend fun getCurrentOrder(tableId: Int): Flow<Resource<Order?>> = flow {
        emit(Resource.Loading())

        try {
            val result = NetworkUtils.safeApiCall { tableApiService.getCurrentOrder(tableId) }

            when (result) {
                is NetworkResult.Success -> {
                    val payload = result.data as? CurrentOrderPayload
                    emit(Resource.Success(payload?.order))
                }
                is NetworkResult.Error -> {
                    // Try to get cached current order - but we don't have currentOrderId in database
                    // So we'll just return null for now
                    emit(Resource.Success(null))
                }
                is NetworkResult.Loading -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            emit(Resource.Error(e.message ?: "Failed to get current order"))
        }
    }

    // Local operations
    suspend fun getTableById(tableId: Int): Table? {
        return tableDao.getTableById(tableId)
    }

    suspend fun getTablesByStatus(status: String): List<Table> {
        return tableDao.getTablesByStatus(status)
    }

    fun getAvailableTablesFlow(): Flow<List<Table>> {
        return tableDao.getAllTablesFlow().map { tables ->
            tables.filter { it.status == "available" }
        }
    }

    fun getOccupiedTablesFlow(): Flow<List<Table>> {
        return tableDao.getAllTablesFlow().map { tables ->
            tables.filter { it.status == "occupied" }
        }
    }
}


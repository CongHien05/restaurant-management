package com.restaurant.staff.ui.tables

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import android.util.Log
import com.restaurant.staff.data.model.Area
import com.restaurant.staff.data.model.Table
import com.restaurant.staff.data.repository.TableRepository
import kotlinx.coroutines.launch

class TablesViewModel(private val repository: TableRepository) : ViewModel() {

    private val _tables = MutableLiveData<List<Table>>()
    val tables: LiveData<List<Table>> = _tables

    private val _areas = MutableLiveData<List<Area>>()
    val areas: LiveData<List<Area>> = _areas

    private val _isLoading = MutableLiveData<Boolean>()
    val isLoading: LiveData<Boolean> = _isLoading

    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error

    private var selectedAreaId: Int? = null

    init {
        loadAreas()
        loadTables()
    }

    fun loadAreas() {
        viewModelScope.launch {
            try {
                _isLoading.value = true
                _error.value = null

                Log.d("TablesViewModel", "Loading areas...")
                val areasResult = repository.getAreas()
                Log.d("TablesViewModel", "Areas loaded: ${areasResult.size} areas")
                _areas.value = areasResult

            } catch (e: Exception) {
                Log.e("TablesViewModel", "Error loading areas", e)
                _error.value = "Lỗi khi tải danh sách khu vực: ${e.message}"
            } finally {
                _isLoading.value = false
            }
        }
    }

    fun loadTables(areaId: Int? = null) {
        viewModelScope.launch {
            try {
                _isLoading.value = true
                _error.value = null
                selectedAreaId = areaId

                Log.d("TablesViewModel", "Loading tables for areaId: $areaId")
                val tablesResult = repository.getTables(
                    page = 1,
                    limit = 50,
                    areaId = areaId
                )
                Log.d("TablesViewModel", "Tables loaded: ${tablesResult.size} tables")
                tablesResult.forEach { table ->
                    Log.d("TablesViewModel", "Table ${table.name}: pendingAmount=${table.pendingAmount}, activeOrders=${table.activeOrders}")
                }
                _tables.value = tablesResult

            } catch (e: Exception) {
                Log.e("TablesViewModel", "Error loading tables", e)
                _error.value = "Lỗi khi tải danh sách bàn: ${e.message}"
            } finally {
                _isLoading.value = false
            }
        }
    }

    fun refreshTables() {
        loadTables(selectedAreaId)
    }

    fun filterByArea(areaId: Int?) {
        loadTables(areaId)
    }

    fun updateTableStatus(tableId: Int, status: String) {
        viewModelScope.launch {
            try {
                repository.updateTableStatus(tableId, status)
                // Refresh tables after update
                refreshTables()
            } catch (e: Exception) {
                _error.value = "Lỗi khi cập nhật trạng thái bàn: ${e.message}"
            }
        }
    }

    fun clearError() {
        _error.value = null
    }
}

class TablesViewModelFactory(
    private val repository: TableRepository
) : ViewModelProvider.Factory {
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(TablesViewModel::class.java)) {
            @Suppress("UNCHECKED_CAST")
            return TablesViewModel(repository) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}

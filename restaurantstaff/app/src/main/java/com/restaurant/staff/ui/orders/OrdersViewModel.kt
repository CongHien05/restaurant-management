package com.restaurant.staff.ui.orders

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.restaurant.staff.data.model.Order
import com.restaurant.staff.data.model.Resource
import com.restaurant.staff.data.repository.OrderRepository
import com.restaurant.staff.utils.PreferenceManager
import kotlinx.coroutines.launch

class OrdersViewModel(
    private val repository: OrderRepository,
    private val prefs: PreferenceManager
) : ViewModel() {

    private val _orders = MutableLiveData<List<Order>>(emptyList())
    val orders: LiveData<List<Order>> = _orders

    private val _isLoading = MutableLiveData<Boolean>(false)
    val isLoading: LiveData<Boolean> = _isLoading

    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error

    fun loadOrders(status: String? = null) {
        viewModelScope.launch {
            try {
                _isLoading.value = true
                _error.value = null
                repository.getOrders(page = 1, limit = 50, status = status).collect { resource ->
                    when (resource) {
                        is Resource.Success -> {
                            _orders.value = resource.data
                        }
                        is Resource.Error -> {
                            _error.value = resource.message
                        }
                        is Resource.Loading -> {
                            // Keep loading state
                        }
                    }
                }
            } catch (e: Exception) {
                _error.value = e.message ?: "Lỗi khi tải danh sách đơn hàng"
            } finally {
                _isLoading.value = false
            }
        }
    }

    fun refresh() {
        loadOrders()
    }

    fun clearError() {
        _error.value = null
    }
}

class OrdersViewModelFactory(
    private val repository: OrderRepository,
    private val prefs: PreferenceManager
) : ViewModelProvider.Factory {
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(OrdersViewModel::class.java)) {
            @Suppress("UNCHECKED_CAST")
            return OrdersViewModel(repository, prefs) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}



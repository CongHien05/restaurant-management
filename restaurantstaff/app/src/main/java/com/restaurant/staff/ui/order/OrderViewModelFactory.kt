package com.restaurant.staff.ui.order

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.restaurant.staff.data.repository.MenuRepository
import com.restaurant.staff.data.repository.OrderRepository

class OrderViewModelFactory(
    private val menuRepository: MenuRepository,
    private val orderRepository: OrderRepository
) : ViewModelProvider.Factory {

    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(OrderViewModelSimplified::class.java)) {
            return OrderViewModelSimplified(menuRepository, orderRepository) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}

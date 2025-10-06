package com.restaurant.staff

import android.app.Application
import com.restaurant.staff.data.local.AppDatabase
import com.restaurant.staff.data.remote.ApiClient
import com.restaurant.staff.data.repository.AuthRepository
import com.restaurant.staff.data.repository.MenuRepository
import com.restaurant.staff.data.repository.OrderRepository
import com.restaurant.staff.data.repository.TableRepository
import com.restaurant.staff.utils.PreferenceManager
import com.restaurant.staff.utils.DatabaseHelper
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class RestaurantStaffApplication : Application() {

    // Database
    val database: AppDatabase by lazy { AppDatabase.getDatabase(this) }

    // Preference Manager
    val preferenceManager: PreferenceManager by lazy { PreferenceManager(this) }

    // Repositories
    val authRepository: AuthRepository by lazy {
        AuthRepository(ApiClient.authService, preferenceManager)
    }

    val tableRepository: TableRepository by lazy {
        TableRepository(ApiClient.tableService, database.tableDao(), database.areaDao())
    }

    val menuRepository: MenuRepository by lazy {
        MenuRepository(ApiClient.menuService, database.menuDao())
    }

    val orderRepository: OrderRepository by lazy {
        OrderRepository(ApiClient.orderService, database.orderDao(), database.tableDao(), ApiClient.tableService)
    }

    override fun onCreate() {
        super.onCreate()

        // Initialize any global configurations
        setupApiClient()

        // Uncomment the line below if you need to clear database for testing
        // DatabaseHelper.clearDatabase(this)
    }

    private fun setupApiClient() {
        // Setup API client with base URL and interceptors
        // Restore auth token if user is logged in
        if (preferenceManager.isLoggedIn()) {
            ApiClient.setAuthToken(preferenceManager.getAuthToken())
        }
    }

    fun updateAuthToken(token: String?) {
        preferenceManager.saveAuthToken(token)
        ApiClient.setAuthToken(token)
    }
}

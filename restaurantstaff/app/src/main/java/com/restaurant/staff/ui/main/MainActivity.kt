package com.restaurant.staff.ui.main

import android.content.Intent
import android.os.Bundle
import android.view.Menu
import android.view.MenuItem
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import androidx.navigation.findNavController
import androidx.navigation.ui.AppBarConfiguration
import androidx.navigation.ui.setupActionBarWithNavController
import androidx.navigation.ui.setupWithNavController
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import com.restaurant.staff.R
import com.restaurant.staff.RestaurantStaffApplication
import com.restaurant.staff.data.model.Resource
import com.restaurant.staff.data.remote.ApiClient
import com.restaurant.staff.databinding.ActivityMainBinding
import com.restaurant.staff.ui.auth.LoginActivity
import com.restaurant.staff.ui.auth.LoginViewModel
import com.restaurant.staff.ui.auth.LoginViewModelFactory
import com.restaurant.staff.utils.showToast
import kotlinx.coroutines.launch

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private val authViewModel: LoginViewModel by viewModels {
        LoginViewModelFactory((application as RestaurantStaffApplication).authRepository)
    }

    private var isNavigatingToLogin = false
    private var isLoggingOut = false

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setupToolbar()
        setupNavigation()
        checkAuthStatus()
    }

    private fun setupToolbar() {
        setSupportActionBar(binding.toolbar)
        supportActionBar?.title = "Restaurant Staff"
    }

    private fun setupNavigation() {
        // Use supportFragmentManager to get NavController safely
        val navHostFragment = supportFragmentManager
            .findFragmentById(R.id.nav_host_fragment) as androidx.navigation.fragment.NavHostFragment
        val navController = navHostFragment.navController

        val appBarConfiguration = AppBarConfiguration(
            setOf(R.id.nav_tables, R.id.nav_menu, R.id.nav_orders, R.id.nav_profile)
        )

        setupActionBarWithNavController(navController, appBarConfiguration)
        binding.bottomNavigation.setupWithNavController(navController)
    }

    private fun checkAuthStatus() {
        // Check if user is still logged in and token is valid
        if (!authViewModel.isLoggedIn() || ApiClient.isTokenExpired()) {
            android.util.Log.d("MainActivity", "User not logged in or token expired, auto logging out")
            if (!isNavigatingToLogin && !isLoggingOut) {
                // Auto logout when token is expired
                autoLogout()
            }
        } else {
            android.util.Log.d("MainActivity", "User logged in and token valid")
        }
    }

    private fun autoLogout() {
        isLoggingOut = true
        android.util.Log.d("MainActivity", "Auto logout due to token expiration")

        // Clear auth data immediately
        lifecycleScope.launch {
            authViewModel.logout().collect { resource ->
                when (resource) {
                    is Resource.Success -> {
                        android.util.Log.d("MainActivity", "Auto logout successful, navigating to login")
                        navigateToLogin()
                    }
                    is Resource.Error -> {
                        android.util.Log.d("MainActivity", "Auto logout error: ${resource.message}, still navigating to login")
                        navigateToLogin()
                    }
                    is Resource.Loading -> {
                        android.util.Log.d("MainActivity", "Auto logout in progress...")
                    }
                }
            }
        }
    }

    override fun onCreateOptionsMenu(menu: Menu): Boolean {
        menuInflater.inflate(R.menu.main_menu, menu)
        return true
    }

    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        return when (item.itemId) {
            R.id.action_logout -> {
                showLogoutDialog()
                true
            }

            R.id.action_refresh -> {
                // Refresh tables data
                refreshTablesData()
                showToast("Đang làm mới...")
                true
            }
            else -> super.onOptionsItemSelected(item)
        }
    }

    private fun showLogoutDialog() {
        MaterialAlertDialogBuilder(this)
            .setTitle("Đăng xuất")
            .setMessage("Bạn có chắc chắn muốn đăng xuất?")
            .setPositiveButton("Đăng xuất") { _, _ ->
                logout()
            }
            .setNegativeButton("Hủy", null)
            .show()
    }

    private fun logout() {
        isLoggingOut = true
        lifecycleScope.launch {
            authViewModel.logout().collect { resource ->
                when (resource) {
                    is Resource.Success -> {
                        android.util.Log.d("MainActivity", "Logout successful, navigating to login")
                        navigateToLogin()
                    }
                    is Resource.Error -> {
                        android.util.Log.d("MainActivity", "Logout error: ${resource.message}, still navigating to login")
                        showToast("Lỗi đăng xuất: ${resource.message}")
                        // Still navigate to login even if logout fails
                        navigateToLogin()
                    }
                    is Resource.Loading -> {
                        android.util.Log.d("MainActivity", "Logout in progress...")
                        // Show loading if needed
                    }
                }
            }
        }
    }

    private fun navigateToLogin() {
        if (!isNavigatingToLogin) {
            isNavigatingToLogin = true
            android.util.Log.d("MainActivity", "Navigating to LoginActivity")
            val intent = Intent(this, LoginActivity::class.java)
            intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            startActivity(intent)
            finish()
        }
    }

    override fun onResume() {
        super.onResume()
        isNavigatingToLogin = false
        isLoggingOut = false

        // Check token expiration when app resumes
        if (ApiClient.isTokenExpired()) {
            android.util.Log.w("MainActivity", "Token expired on resume, auto logging out")
            if (!isNavigatingToLogin && !isLoggingOut) {
                autoLogout()
            }
        } else {
            // Refresh tables data when returning to app
            refreshTablesData()
        }
    }

    private fun refreshTablesData() {
        // Find TablesFragment and refresh its data
        val navHostFragment = supportFragmentManager
            .findFragmentById(R.id.nav_host_fragment) as androidx.navigation.fragment.NavHostFragment
        val navController = navHostFragment.navController

        // Get current fragment
        val currentFragment = navController.currentDestination?.let { destination ->
            supportFragmentManager.findFragmentById(destination.id)
        }

        // Refresh TablesFragment if it's the current fragment
        if (currentFragment is com.restaurant.staff.ui.tables.TablesFragment) {
            currentFragment.refreshTables()
        }

        // Also refresh TablesFragment if it exists in the back stack
        val tablesFragment = supportFragmentManager.fragments.find {
            it is com.restaurant.staff.ui.tables.TablesFragment
        } as? com.restaurant.staff.ui.tables.TablesFragment

        tablesFragment?.refreshTables()
    }

    override fun onSupportNavigateUp(): Boolean {
        val navHostFragment = supportFragmentManager
            .findFragmentById(R.id.nav_host_fragment) as androidx.navigation.fragment.NavHostFragment
        val navController = navHostFragment.navController
        return navController.navigateUp() || super.onSupportNavigateUp()
    }
}


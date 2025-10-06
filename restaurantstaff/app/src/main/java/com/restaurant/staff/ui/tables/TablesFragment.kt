package com.restaurant.staff.ui.tables

import android.content.Context
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.appcompat.widget.SearchView
import androidx.core.content.ContextCompat
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.GridLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.google.android.material.chip.Chip
import com.restaurant.staff.R
import com.restaurant.staff.RestaurantStaffApplication
import com.restaurant.staff.data.model.Area
import com.restaurant.staff.data.model.Table
import com.restaurant.staff.utils.hasActiveOrder
import com.restaurant.staff.databinding.FragmentTablesBinding
import com.restaurant.staff.ui.order.OrderFragment
import com.restaurant.staff.utils.showToast
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import androidx.navigation.fragment.findNavController

class TablesFragment : Fragment() {

    private var _binding: FragmentTablesBinding? = null
    private val binding get() = _binding!!

    private val viewModel: TablesViewModel by viewModels {
        TablesViewModelFactory(
            (requireActivity().application as RestaurantStaffApplication).tableRepository
        )
    }
    private lateinit var tablesAdapter: TablesAdapter

    // Search and filter state
    private var currentSearchQuery = ""
    private var currentStatusFilter = ""
    private var currentAreaFilter = ""
    private var lastVisitedTableId: Int? = null
    private var searchJob: Job? = null
    private var pollingJob: Job? = null

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentTablesBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        viewAlive = true

        setupRecyclerView()
        setupSearchView()
        setupFilterChips()
        setupClickListeners()
        observeViewModel()

        // Load initial data
        viewModel.loadAreas()
        viewModel.loadTables()

        // Restore last table position
        restoreLastTablePosition()
    }

    private fun setupRecyclerView() {
        tablesAdapter = TablesAdapter { table ->
            onTableClick(table)
        }

        binding.recyclerViewTables.apply {
            adapter = tablesAdapter
            layoutManager = GridLayoutManager(context, 2)

            // Save scroll position when scrolling
            addOnScrollListener(object : RecyclerView.OnScrollListener() {
                override fun onScrollStateChanged(recyclerView: RecyclerView, newState: Int) {
                    super.onScrollStateChanged(recyclerView, newState)
                    if (newState == RecyclerView.SCROLL_STATE_IDLE) {
                        saveCurrentPosition()
                    }
                }
            })
        }
    }

    private fun setupSearchView() {
        binding.searchView.setOnQueryTextListener(object : SearchView.OnQueryTextListener {
            override fun onQueryTextSubmit(query: String?): Boolean {
                performSearch(query ?: "")
                return true
            }

            override fun onQueryTextChange(newText: String?): Boolean {
                // Debounce search
                searchJob?.cancel()
                searchJob = lifecycleScope.launch {
                    delay(300) // Wait 300ms after user stops typing
                    performSearch(newText ?: "")
                }
                return true
            }
        })
    }

    private fun setupFilterChips() {
        // Quick filter chips
        binding.chipAll.setOnCheckedChangeListener { _, isChecked ->
            if (isChecked) {
                // Reset all filters when "Tất cả" is selected
                currentStatusFilter = ""
                currentSearchQuery = ""
                currentAreaFilter = ""

                // Clear search view
                binding.searchView.setQuery("", false)

                // Reset area chips
                binding.chipGroupAreas.clearCheck()
                binding.chipAllAreas.isChecked = true

                // Hide search info
                binding.layoutSearchInfo.visibility = View.GONE

                android.util.Log.d("TablesFragment", "Chip 'Tất cả' selected - resetting all filters")
                applyFilters()
            }
        }

        binding.chipOccupied.setOnCheckedChangeListener { _, isChecked ->
            if (isChecked) {
                currentStatusFilter = "occupied"
                applyFilters()
            }
        }

        binding.chipAvailable.setOnCheckedChangeListener { _, isChecked ->
            if (isChecked) {
                currentStatusFilter = "available"
                applyFilters()
            }
        }

        binding.chipHasOrder.setOnCheckedChangeListener { _, isChecked ->
            if (isChecked) {
                currentStatusFilter = "has_order"
                applyFilters()
            }
        }
    }

    private fun setupClickListeners() {
        binding.btnFilter.setOnClickListener {
            toggleFilterVisibility()
        }

        binding.btnClearSearch.setOnClickListener {
            clearSearch()
        }

        // Area filter chips
        binding.chipGroupAreas.setOnCheckedStateChangeListener { _, checkedIds ->
            if (checkedIds.isEmpty()) {
                // If no chip is selected, show all areas
                currentAreaFilter = ""
                applyFilters()
            } else {
                val selectedChip = binding.chipGroupAreas.findViewById<Chip>(checkedIds.first())
                val areaName = selectedChip.text.toString()
                currentAreaFilter = areaName
                android.util.Log.d("TablesFragment", "Area filter selected: '$areaName'")
                applyFilters()
            }
        }
    }

    private fun observeViewModel() {
        // Observe areas
        viewModel.areas.observe(viewLifecycleOwner) { areas ->
            android.util.Log.d("TablesFragment", "Areas loaded: ${areas.size} areas")
            setupAreaChips(areas)
        }

        viewModel.tables.observe(viewLifecycleOwner) { tables ->
            android.util.Log.d("TablesFragment", "Tables loaded: ${tables.size} tables")
            tables.forEach { table ->
                android.util.Log.d("TablesFragment", "Table: ${table.name}, status: ${table.status}, area: ${table.areaName}")
            }
            applyFiltersToTables(tables)
            updateSearchResultsInfo()
        }

        viewModel.isLoading.observe(viewLifecycleOwner) { isLoading ->
            binding.layoutLoading.visibility = if (isLoading) View.VISIBLE else View.GONE
        }

        viewModel.error.observe(viewLifecycleOwner) { error ->
            error?.let {
                android.util.Log.e("TablesFragment", "Error: $it")
                requireContext().showToast("Lỗi: $it")
            }
        }
    }

    private fun performSearch(query: String) {
        currentSearchQuery = query
        applyFilters()
    }

    private fun clearSearch() {
        binding.searchView.setQuery("", false)
        currentSearchQuery = ""
        binding.layoutSearchInfo.visibility = View.GONE
        applyFilters()
    }

    private fun applyFilters() {
        viewModel.tables.value?.let { tables ->
            applyFiltersToTables(tables)
        }
    }

    private fun applyFiltersToTables(tables: List<Table>) {
        android.util.Log.d("TablesFragment", "Applying filters: search='$currentSearchQuery', status='$currentStatusFilter', area='$currentAreaFilter'")
        android.util.Log.d("TablesFragment", "Total tables before filter: ${tables.size}")

        val filteredTables = tables.filter { table ->
            var matches = true

            // Search filter
            if (currentSearchQuery.isNotEmpty()) {
                val query = currentSearchQuery.lowercase()
                val matchesSearch = table.name.contains(query) ||
                        (table.tableName?.lowercase()?.contains(query) == true) ||
                        (table.areaName?.lowercase()?.contains(query) == true)
                matches = matches && matchesSearch
                android.util.Log.d("TablesFragment", "Table ${table.name} search match: $matchesSearch")
            }

            // Status filter
            if (currentStatusFilter.isNotEmpty()) {
                when (currentStatusFilter) {
                    "occupied" -> matches = matches && table.status == "occupied"
                    "available" -> matches = matches && table.status == "available"
                    "has_order" -> matches = matches && table.hasActiveOrder
                }
                android.util.Log.d("TablesFragment", "Table ${table.name} status match: $matches (status=${table.status}, hasOrder=${table.hasActiveOrder})")
            }

            // Area filter
            if (currentAreaFilter.isNotEmpty() && currentAreaFilter != "Tất cả khu vực") {
                matches = matches && table.areaName == currentAreaFilter
                android.util.Log.d("TablesFragment", "Table ${table.name} area match: $matches (area=${table.areaName}, filter=$currentAreaFilter)")
            }

            android.util.Log.d("TablesFragment", "Table ${table.name} final match: $matches")
            matches
        }

        android.util.Log.d("TablesFragment", "Total tables after filter: ${filteredTables.size}")
        tablesAdapter.submitList(filteredTables)
        updateSearchResultsInfo()
    }

    private fun updateSearchResultsInfo() {
        val currentList = tablesAdapter.currentList
        val totalTables = viewModel.tables.value?.size ?: 0

        if (currentSearchQuery.isNotEmpty() || currentStatusFilter.isNotEmpty() || currentAreaFilter.isNotEmpty()) {
            binding.layoutSearchInfo.visibility = View.VISIBLE
            binding.textSearchResults.text = "Tìm thấy ${currentList.size}/$totalTables bàn"
        } else {
            binding.layoutSearchInfo.visibility = View.GONE
        }
    }

    private fun toggleFilterVisibility() {
        val isVisible = binding.scrollQuickFilters.visibility == View.VISIBLE
        binding.scrollQuickFilters.visibility = if (isVisible) View.GONE else View.VISIBLE
    }

    private fun onTableClick(table: Table) {
        // Save last visited table
        saveLastVisitedTable(table.id)

        // Navigate to order fragment using Navigation Component
        val bundle = Bundle().apply {
            putInt("tableId", table.id)
        }
        findNavController().navigate(R.id.nav_order, bundle)
    }

    private fun saveLastVisitedTable(tableId: Int) {
        lastVisitedTableId = tableId
        val prefs = requireContext().getSharedPreferences("table_prefs", Context.MODE_PRIVATE)
        prefs.edit().putInt("last_visited_table", tableId).apply()

        android.util.Log.d("TablesFragment", "Saved last visited table: $tableId")

        // Update adapter
        tablesAdapter.setLastVisitedTable(tableId)
    }

    private var viewAlive: Boolean = false

    private fun restoreLastTablePosition() {
        if (!viewAlive || _binding == null) return
        val prefs = requireContext().getSharedPreferences("table_prefs", Context.MODE_PRIVATE)
        lastVisitedTableId = prefs.getInt("last_visited_table", -1).takeIf { it != -1 }

        if (lastVisitedTableId != null) {
            android.util.Log.d("TablesFragment", "Restoring last visited table: $lastVisitedTableId")
            // Update adapter to highlight last visited table
            tablesAdapter.setLastVisitedTable(lastVisitedTableId)

            // Scroll to table after a short delay to ensure RecyclerView is ready
            view?.postDelayed({
                if (!viewAlive || _binding == null) return@postDelayed
                lastVisitedTableId?.let { scrollToTable(it) }
            }, 300)
        }
    }

    private fun scrollToTable(tableId: Int) {
        if (!viewAlive || _binding == null) return
        val tables = tablesAdapter.currentList
        val position = tables.indexOfFirst { it.id == tableId }

        if (position != -1) {
            android.util.Log.d("TablesFragment", "Scrolling to table $tableId at position $position")
            binding.recyclerViewTables.post {
                if (!viewAlive || _binding == null) return@post
                // Use smoothScrollToPosition for better UX
                binding.recyclerViewTables.smoothScrollToPosition(position)

                // Also update adapter highlighting
                tablesAdapter.setLastVisitedTable(tableId)
            }
        } else {
            android.util.Log.w("TablesFragment", "Table $tableId not found in current list")
        }
    }

    private fun saveCurrentPosition() {
        val layoutManager = binding.recyclerViewTables.layoutManager as? GridLayoutManager
        layoutManager?.let {
            val position = it.findFirstVisibleItemPosition()
            val prefs = requireContext().getSharedPreferences("table_prefs", Context.MODE_PRIVATE)
            prefs.edit().putInt("scroll_position", position).apply()
        }
    }

    fun refreshTables() {
        viewModel.loadTables()
    }

    private fun startPolling() {
        stopPolling() // Cancel any existing polling
        pollingJob = viewLifecycleOwner.lifecycleScope.launch {
            while (true) {
                delay(5000) // Poll every 5 seconds
                if (viewAlive && _binding != null) {
                    android.util.Log.d("TablesFragment", "Polling: Refreshing tables")
                    viewModel.loadTables()
                }
            }
        }
    }

    private fun stopPolling() {
        pollingJob?.cancel()
        pollingJob = null
    }

    override fun onResume() {
        super.onResume()
        android.util.Log.d("TablesFragment", "onResume: Restoring last table position")

        // Start polling for table updates
        startPolling()

        // Restore last table position after a short delay to ensure data is loaded
        view?.postDelayed({
            if (!viewAlive || _binding == null) return@postDelayed
            restoreLastTablePosition()
        }, 500)
    }

    override fun onPause() {
        super.onPause()
        stopPolling()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        searchJob?.cancel()
        stopPolling()
        viewAlive = false
        _binding = null
    }

    private fun setupAreaChips(areas: List<Area>) {
        // Clear existing chips except "All areas"
        val allAreasChip = binding.chipAllAreas
        binding.chipGroupAreas.removeAllViews()
        binding.chipGroupAreas.addView(allAreasChip)

        // Add chips for each area
        areas.forEach { area ->
            val chip = Chip(requireContext()).apply {
                text = area.name
                tag = area.id
                isCheckable = true
                setChipBackgroundColorResource(R.color.surface)
                setTextColor(ContextCompat.getColor(context, R.color.on_surface))
            }
            binding.chipGroupAreas.addView(chip)
        }

        // Select "All areas" by default
        binding.chipAllAreas.isChecked = true
    }
}

